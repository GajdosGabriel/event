<?php

use Carbon\Carbon;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Naplánuje artisan príkaz tak, aby bežal v tom istom procese ako schedule:run.
 *
 * Schedule::command() príkaz nespustí priamo — nechá shell naštartovať nové PHP
 * (`sh -c "'/usr/php84/bin/php' 'artisan' app:ai-detector > /dev/null 2>&1"`)
 * a čaká naň. Na tomto hostingu to 25. 8. 2026 skončilo návratovým kódom 126:
 * shell binárku nájde, ale nesmie ju spustiť, takže príkaz vôbec nezbehol.
 * Artisan::call() žiadny shell ani druhé PHP nepotrebuje — presne takto beží aj
 * ručné spustenie z /admin/tools (viď AdminToolsController).
 *
 * Beh sa tým nespomalí, skôr naopak: webcron volá schedule:run v HTTP requeste
 * a ten na každý podproces aj tak čakal, pričom si každý nanovo bootoval celý
 * framework.
 *
 * Cenou je spoločný proces — fatálna chyba v jednom príkaze ukončí celý beh
 * webcronu vrátane heartbeatu (viď App\Support\CronHeartbeat), takže výpadok
 * aspoň neostane ticho.
 *
 * Meno musí byť jednoznačné aj s parametrami: withoutOverlapping() z neho
 * odvodzuje zámok a bez parametrov by si tri importy delili jeden.
 */
$artisan = function (string $command, array $parameters = []): CallbackEvent {
    $name = collect($parameters)
        ->map(fn ($value, $key) => match (true) {
            is_int($key) => (string) $value,
            $value === true => $key,
            default => $key . '=' . $value,
        })
        ->prepend($command)
        ->implode(' ');

    return Schedule::call(function () use ($command, $parameters) {
        $exitCode = Artisan::call($command, $parameters);

        if ($exitCode !== 0) {
            // Vlastný zápis do logu: pri closure scheduler meno príkazu nepozná
            // a zapísal by len „Scheduled command [] failed".
            Log::warning('Naplánovaný príkaz skončil s nenulovým návratovým kódom.', [
                'command' => $command,
                'parameters' => $parameters,
                'exit_code' => $exitCode,
            ]);
        }
    })->name($name);
};

$artisan('app:ai-detector')->everyMinute();
$artisan('app:events-archive-finished')->everyTenMinutes();
// Naplánované publikovanie. Päť minút je kompromis: organizátor plánuje na
// celé hodiny, takže presnosť na minútu nikto nepotrebuje, a beh je lacný
// (indexovaný dopyt nad status + publish_at, väčšinou prázdny).
$artisan('app:events-publish-scheduled')->everyFiveMinutes();
$artisan('app:tickets-expire-unconfirmed')->everyTenMinutes();
// Pripomienky účastníkom. Presnosť na desať minút stačí — organizátor si volí
// hodiny pred akciou, nie minúty.
$artisan('app:events-send-reminders')->everyTenMinutes();
$artisan('app:registrations-expire-pending')->everyTenMinutes();
// Obsahové štítky podujatí. Malá dávka a vlastný časový strop — všetky príkazy
// bežia sekvenčne v jednom webcron requeste spolu s app:ai-detector.
$artisan('app:events-ai-tag')->everyTwoMinutes()->withoutOverlapping();
// Overovanie uložených hodnôt, ktoré môžu prestať fungovať (webové adresy).
// Krátke dávky každých päť minút namiesto jednej veľkej v noci: každý beh
// obsahuje cudzie HTTP dotazy a musí sa zmestiť do webcron requestu spolu
// s ostatnými príkazmi. Zároveň sa tým rýchlo vybaví podnet od návštevníka,
// ktorý klikol na odkaz (viď BrokenLinkReportController).
$artisan('app:attribute-checks-run')->everyFiveMinutes()->withoutOverlapping(10);
// Kontrola zverejnených popisov. Desať minút a malá dávka: každý záznam je
// jedno volanie OpenAI, takže beh je drahší než sondy odkazov, a nikam
// nespěchá — samotné plánovanie už má po zverejnení odklad (viď
// config/content_review.php), aby e-mail neodišiel skôr, než človek dopíše.
$artisan('app:content-reviews-run')->everyTenMinutes()->withoutOverlapping(15);
// Riadky zobrazení slúžia len na dedup a časové štatistiky; trvalý počet je
// v stĺpci views_count, takže mazanie starých riadkov oň nepripraví.
$artisan('app:views-prune')->dailyAt('03:20');
// Plagáty nahraté bez účtu, ktoré si nikto neprivlastnil — aj so súbormi.
$artisan('app:poster-drafts-prune')->dailyAt('03:40');
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

    $artisan('app:import-event-sources', ['--url' => $sourceUrl])
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
// max-time drží beh pod minútou, aby sa jednotlivé behy neprekrývali. Kontroluje
// sa medzi jobmi: bez rozšírenia pcntl (vo webovom SAPI nebýva) worker neustráži
// `--timeout` jedného jobu, takže zaseknutý job drží webcron request až do
// max_execution_time. Preto majú joby vlastné stropy na HTTP dotazoch.
//
// Fronty sú vymenované explicitne: bez `--queue` berie worker len `default`,
// takže ručný import z /admin/tools (job ide na frontu `imports`) by v tabuľke
// visel donekonečna a UI by navždy hlásilo „čaká v poradí". Poradie určuje
// prioritu — krátke joby z `default` idú prvé, dlhý import až po nich.
$artisan('queue:work', [
    'connection' => 'database',
    '--queue' => 'default,imports',
    '--stop-when-empty' => true,
    '--max-time' => 50,
    '--tries' => 3,
])->everyMinute()->withoutOverlapping();
