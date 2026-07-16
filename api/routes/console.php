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
Schedule::command('app:registrations-expire-pending')->everyTenMinutes();
// Timezone je explicitný, lebo app beží v UTC — bez neho by import šiel o 18:00
// slovenského času v lete a o 17:00 v zime.
Schedule::command('app:import-event-sources')->dailyAt('16:00')->timezone('Europe/Bratislava');
