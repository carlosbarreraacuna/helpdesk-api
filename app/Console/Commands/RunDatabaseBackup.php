<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RunDatabaseBackup extends Command
{
    protected $signature = 'backup:run {--manual}';

    protected $description = 'Genera dump de PostgreSQL + manifiesto de adjuntos S3, sube a S3 y limpia backups vencidos';

    public function handle(BackupService $service): int
    {
        $this->info('Iniciando backup...');

        $backup = $service->createBackup();

        if ($backup->status === 'success') {
            $this->info("Backup exitoso: {$backup->db_dump_path} ({$backup->db_dump_size_bytes} bytes), {$backup->attachments_object_count} objetos en manifiesto.");
            return self::SUCCESS;
        }

        $this->error("Backup falló: {$backup->error_message}");
        return self::FAILURE;
    }
}
