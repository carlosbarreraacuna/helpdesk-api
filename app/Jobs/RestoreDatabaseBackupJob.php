<?php

namespace App\Jobs;

use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestoreDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1000;

    public function __construct(
        public BackupLog $restoreLog,
    ) {}

    public function handle(BackupService $service): void
    {
        $service->executeRestore($this->restoreLog);
    }
}
