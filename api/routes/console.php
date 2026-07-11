<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:ai-detector')->everyMinute();
Schedule::command('app:events-archive-finished')->everyTenMinutes();
Schedule::command('app:tickets-expire-unconfirmed')->everyTenMinutes();
Schedule::command('app:import-event-sources')->hourly();
