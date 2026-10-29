<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Design 5.5: catch payments whose AzamPay callback never arrived. Runs often
// enough that a customer is not left staring at a waiting screen after paying.
Schedule::command('payments:reconcile')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// readme #24/#25: overdue hires are chased by the desk, not the administrator.
// Flag each rental once (status transition de-duplicates) and notify the
// involved staff, branch managers and the customer first thing in the morning.
Schedule::command('rentals:flag-overdue')
    ->dailyAt('07:30')
    ->withoutOverlapping();
