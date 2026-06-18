<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCommentAttachment;
use App\Models\WidgetChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateAttachmentsToS3 extends Command
{
    protected $signature = 'attachments:migrate-to-s3
                            {--dry-run : List what would be migrated without writing anything}
                            {--chunk=100 : Number of records to process per batch}';

    protected $description = 'Copy existing ticket/comment/widget attachments from the local public disk to S3';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = (int) $this->option('chunk');

        $migrated = 0;
        $skipped  = 0;
        $failed   = 0;

        $migrateOne = function (string $path) use ($dryRun, &$migrated, &$skipped, &$failed) {
            if (!Storage::disk('public')->exists($path)) {
                $skipped++;
                return;
            }

            if ($dryRun) {
                $this->line("  [dry-run] migraría: {$path}");
                $migrated++;
                return;
            }

            try {
                $contents = Storage::disk('public')->get($path);
                Storage::disk('s3')->put($path, $contents);
                $migrated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  Error migrando {$path}: {$e->getMessage()}");
            }
        };

        $this->info('Migrando adjuntos de Ticket...');
        Ticket::whereNotNull('attachment_path')->chunkById($chunk, function ($tickets) use ($migrateOne) {
            foreach ($tickets as $ticket) {
                $migrateOne($ticket->attachment_path);
            }
        });

        $this->info('Migrando adjuntos de TicketComment (columnas planas)...');
        TicketComment::whereNotNull('attachment_path')->chunkById($chunk, function ($comments) use ($migrateOne) {
            foreach ($comments as $comment) {
                $migrateOne($comment->attachment_path);
            }
        });

        $this->info('Migrando adjuntos de TicketCommentAttachment...');
        TicketCommentAttachment::withTrashed()->chunkById($chunk, function ($attachments) use ($migrateOne) {
            foreach ($attachments as $attachment) {
                $migrateOne($attachment->path);
            }
        });

        $this->info('Migrando adjuntos de WidgetChatMessage...');
        WidgetChatMessage::whereNotNull('attachment_path')->chunkById($chunk, function ($messages) use ($migrateOne) {
            foreach ($messages as $message) {
                $migrateOne($message->attachment_path);
            }
        });

        $this->newLine();
        $this->info("Listo. Migrados: {$migrated} | Omitidos (no existían): {$skipped} | Fallidos: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
