<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TravelPlan AI: intention requests, follow-ups and arrival reminders (timings in config/ai.php)
Schedule::command('travelplan:send-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
