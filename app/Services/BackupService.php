<?php

namespace App\Services;

use App\Models\BackupLog;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    private function dbConfig(): array
    {
        return config('database.connections.pgsql');
    }

    public function createBackup(?int $triggeredBy = null): BackupLog
    {
        $timestampKey = now()->format('Y-m-d_His');

        $backup = BackupLog::create([
            'type'          => 'backup',
            'status'        => 'pending',
            'timestamp_key' => $timestampKey,
            'triggered_by'  => $triggeredBy,
        ]);

        $backup->update(['status' => 'running', 'started_at' => now()]);

        try {
            $db = $this->dbConfig();
            $tmpDump = storage_path("app/tmp_backup_{$timestampKey}.dump");

            $result = Process::timeout(600)->env(['PGPASSWORD' => $db['password']])->run([
                'pg_dump',
                '-h', $db['host'],
                '-p', (string) $db['port'],
                '-U', $db['username'],
                '-d', $db['database'],
                '-F', 'c',
                '-f', $tmpDump,
            ]);

            if (!$result->successful()) {
                throw new \RuntimeException('pg_dump falló: ' . $result->errorOutput());
            }

            $dumpSize = filesize($tmpDump);
            $dbDumpPath = "backups/db/{$timestampKey}.dump";
            Storage::disk('s3')->put($dbDumpPath, file_get_contents($tmpDump));
            @unlink($tmpDump);

            [$manifestPath, $objectCount] = $this->buildAttachmentsManifest($timestampKey);

            $backup->update([
                'status'                    => 'success',
                'db_dump_path'              => $dbDumpPath,
                'manifest_path'             => $manifestPath,
                'db_dump_size_bytes'        => $dumpSize,
                'attachments_object_count'  => $objectCount,
                'finished_at'               => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Backup falló: ' . $e->getMessage());
            $backup->update([
                'status'       => 'failed',
                'error_message'=> $e->getMessage(),
                'finished_at'  => now(),
            ]);
        }

        $this->purgeOldBackups();

        return $backup->fresh();
    }

    private function buildAttachmentsManifest(string $timestampKey): array
    {
        $client = $this->s3Client();
        $bucket = config('filesystems.disks.s3.bucket');

        $objects = [];
        $params = ['Bucket' => $bucket, 'Prefix' => 'attachments/'];

        do {
            $result = $client->listObjectVersions($params);
            foreach ($result['Versions'] ?? [] as $version) {
                $objects[] = [
                    'path'          => $version['Key'],
                    'version_id'    => $version['VersionId'],
                    'size'          => $version['Size'],
                    'etag'          => $version['ETag'],
                    'last_modified' => (string) $version['LastModified'],
                ];
            }
            $params['KeyMarker'] = $result['NextKeyMarker'] ?? null;
            $params['VersionIdMarker'] = $result['NextVersionIdMarker'] ?? null;
        } while (!empty($result['IsTruncated']));

        $manifestPath = "backups/manifest/{$timestampKey}.json";
        Storage::disk('s3')->put($manifestPath, json_encode($objects, JSON_PRETTY_PRINT));

        return [$manifestPath, count($objects)];
    }

    private function s3Client(): S3Client
    {
        $config = config('filesystems.disks.s3');

        $args = [
            'version'     => 'latest',
            'region'      => $config['region'],
            'credentials' => [
                'key'    => $config['key'],
                'secret' => $config['secret'],
            ],
        ];

        if (!empty($config['endpoint'])) {
            $args['endpoint'] = $config['endpoint'];
            $args['use_path_style_endpoint'] = $config['use_path_style_endpoint'] ?? true;
        }

        return new S3Client($args);
    }

    public function purgeOldBackups(): void
    {
        $retentionDays = (int) env('BACKUP_RETENTION_DAYS', 30);

        $expired = BackupLog::where('type', 'backup')
            ->where('status', 'success')
            ->whereNull('purged_at')
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->get();

        foreach ($expired as $backup) {
            if ($backup->db_dump_path) {
                Storage::disk('s3')->delete($backup->db_dump_path);
            }
            if ($backup->manifest_path) {
                Storage::disk('s3')->delete($backup->manifest_path);
            }
            $backup->update(['purged_at' => now()]);
        }
    }

    public function listAvailableBackups()
    {
        return BackupLog::where('type', 'backup')
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function createPendingRestore(BackupLog $source, int $triggeredBy): BackupLog
    {
        return BackupLog::create([
            'type'             => 'restore',
            'status'           => 'pending',
            'timestamp_key'    => now()->format('Y-m-d_His'),
            'source_backup_id' => $source->id,
            'triggered_by'     => $triggeredBy,
        ]);
    }

    public function executeRestore(BackupLog $restore): BackupLog
    {
        $source = $restore->sourceBackup;
        $restore->update(['status' => 'running', 'started_at' => now()]);

        try {
            Artisan::call('down');

            $db = $this->dbConfig();
            $tmpRestore = storage_path("app/tmp_restore_{$restore->timestamp_key}.dump");

            $contents = Storage::disk('s3')->get($source->db_dump_path);
            file_put_contents($tmpRestore, $contents);

            $result = Process::timeout(900)->env(['PGPASSWORD' => $db['password']])->run([
                'pg_restore',
                '-h', $db['host'],
                '-p', (string) $db['port'],
                '-U', $db['username'],
                '-d', $db['database'],
                '--clean', '--if-exists', '--no-owner',
                $tmpRestore,
            ]);

            @unlink($tmpRestore);

            if (!$result->successful()) {
                throw new \RuntimeException('pg_restore falló: ' . $result->errorOutput());
            }

            // Verificación de salud post-restore
            \Illuminate\Support\Facades\DB::table('migrations')->count();
            \Illuminate\Support\Facades\DB::table('tickets')->count();

            $restore->update(['status' => 'success', 'finished_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Restore falló: ' . $e->getMessage());
            $restore->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
        } finally {
            Artisan::call('up');
        }

        return $restore->fresh();
    }
}
