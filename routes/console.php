<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminders the day before pick-up and the day before return, at the shop's local time
Schedule::command('reservations:send-reminders')
    ->dailyAt(config('notifications.reminder_time', '09:00'))
    ->timezone(config('reservations.timezone', 'Asia/Manila'));
