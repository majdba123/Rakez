<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ai:purge-conversations')->daily();

// Expire overdue negotiation approvals every hour
Schedule::command('negotiations:expire')->hourly();

// Check marketer performance and issue auto-warnings daily at 8 AM
Schedule::command('hr:check-performance-warnings')->dailyAt('08:00');

// Check credit financing deadlines every hour
Schedule::command('credit:check-deadlines')->hourly();

// Rakiz AI v2 self-feeding: reconcile index every 5 min, full reindex nightly
Schedule::command('ai:reconcile-index')->everyFiveMinutes();
Schedule::command('ai:daily-reindex')->dailyAt('02:00');
