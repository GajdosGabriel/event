<?php

use Carbon\Carbon;
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
// Obsahové štítky podujatí. Malá dávka a vlastný časový strop — všetky príkazy
// bežia sekvenčne v jednom webcron requeste spolu s app:ai-detector.
Schedule::command('app:events-ai-tag')->everyTwoMinutes()->withoutOverlapping();
// Riadky zobrazení slúžia len na dedup a časové štatistiky; trvalý počet je
// v stĺpci views_count, takže mazanie starých riadkov oň nepripraví.
Schedule::command('app:views-prune')->dailyAt('03:20');
// Plagáty nahraté bez účtu, ktoré si nikto neprivlastnil — aj so súbormi.
Schedule::command('app:poster-drafts-prune')->dailyAt('03:40');
// Každý zdroj má vlastný beh s vlastným časom. Kým išli všetky v jednom
// príkaze za sebou, posledný z nich hladoval: 27. 7. 2026 zjedli ecav.sk a
// tkkbs.sk 13 minút a na vyveska.sk sa už nedostalo — hosting nemá shell,
// schedule:run volá webcron, a taký dlhý HTTP request sa nedobehne. Takto je
// každý beh krátky a žiadny zdroj nezávisí od toho predošlého.
//
// Timezone je explicitný, lebo app beží v UTC — bez neho by import šiel o 18:00
// slovenského času v lete a o 17:00 v zime.
foreach (array_values((array) config('services.imports.sources.urls', [])) as $index => $sourceUrl) {
    $startsAt = Carbon::createFromTime(16, 0)->addMinutes(20 * $index)->format('H:i');

    Schedule::command('app:import-event-sources', ['--url' => $sourceUrl])
        ->dailyAt($startsAt)
        ->timezone('Europe/Bratislava')
        // Strop je kratší než rozostup medzi zdrojmi, aby zaseknutý zámok
        // nezablokoval zajtrajší beh toho istého zdroja.
        ->withoutOverlapping(15);
}

// Spracovanie fronty. Hosting nemá shell, takže klasický `queue:work` daemon
// tu bežať nemôže — webcron ale volá schedule:run každú minútu, čo stačí na
// krátky beh, ktorý po vyprázdnení fronty sám skončí.
//
// Connection je uvedená explicitne: pri QUEUE_CONNECTION=sync by `queue:work`
// bez nej spadol, takto len nájde prázdnu tabuľku a hneď skončí. Vďaka tomu je
// prepnutie na QUEUE_CONNECTION=database čisto vec .env, bez zásahu do kódu.
//
// max-time drží beh pod minútou, aby sa jednotlivé behy neprekrývali.
//
// Fronty sú vymenované explicitne: bez `--queue` berie worker len `default`,
// takže ručný import z /admin/tools (job ide na frontu `imports`) by v tabuľke
// visel donekonečna a UI by navždy hlásilo „čaká v poradí". Poradie určuje
// prioritu — krátke joby z `default` idú prvé, dlhý import až po nich.
Schedule::command('queue:work database --queue=default,imports --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
