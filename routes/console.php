<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Renew Gmail Pub/Sub watch every 6 days (Google expires after 7 days)
Schedule::command('gmail:watch')->weekly();

// Auto-close tickets where the validation deadline has expired
Schedule::job(new \App\Jobs\AutoCloseExpiredValidations)->hourly();
