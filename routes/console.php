<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Renew Gmail Pub/Sub watch daily (Google watch expires after 7 days — daily renewal is safe)
Schedule::command('gmail:watch')->daily();

// Auto-close tickets where the validation deadline has expired
Schedule::job(new \App\Jobs\AutoCloseExpiredValidations)->hourly();

// Check for SLA breaches every 15 minutes and notify agents + supervisors
Schedule::job(new \App\Jobs\CheckSlaBreaches)->everyFifteenMinutes();

// Purge auth logs older than 1 year — runs daily at 2 AM
Schedule::job(new \App\Jobs\PurgeOldAuthLogs)->dailyAt('02:00');

// Purge expired and old revoked refresh tokens daily at 3 AM
Schedule::job(new \App\Jobs\PurgeExpiredRefreshTokens)->dailyAt('03:00');

// Daily database + attachments manifest backup to S3/R2 — 02:30 AM
Schedule::job(new \App\Jobs\RunDatabaseBackupJob)->dailyAt('02:30');
