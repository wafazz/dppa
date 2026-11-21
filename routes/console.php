<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The server needs one cron entry that runs `php artisan schedule:run` every minute.
Schedule::command('pa:send-email-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('pa:send-management-digest')->weeklyOn(1, '08:00')->withoutOverlapping();
