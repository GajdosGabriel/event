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

// Spracovanie fronty. Hosting nemá shell, takže klasický `queue:work` daemon
// tu bežať nemôže — webcron ale volá schedule:run každú minútu, čo stačí na
// krátky beh, ktorý po vyprázdnení fronty sám skončí.
//
// Connection je uvedená explicitne: pri QUEUE_CONNECTION=sync by `queue:work`
// bez nej spadol, takto len nájde prázdnu tabuľku a hneď skončí. Vďaka tomu je
// prepnutie na QUEUE_CONNECTION=database čisto vec .env, bez zásahu do kódu.
//
// max-time drží beh pod minútou, aby sa jednotlivé behy neprekrývali.
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
