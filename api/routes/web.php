<?php

use App\Http\Controllers\Public\SitemapController;
use App\Support\CronHeartbeat;
use App\Support\CronToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Mapa stránok na koreni SPA hostu.
 *
 * Tá istá routa je aj pod `/api` (routes/api.php). Duplicita nie je omyl:
 * Apache prepisuje `/sitemap.xml` na front controller, ale Laravel smeruje
 * podľa pôvodného `REQUEST_URI`, ktorý interný prepis nemení — pod `/api`
 * definovaná routa sa preto na koreňovú adresu nikdy netrafí a Search Console
 * dostávala 404.
 */
Route::get('/sitemap.xml', SitemapController::class)
    ->name('public.sitemap.root')
    ->middleware('throttle:60,1');

Route::get('/login', function () {
    return null;
})->name('login');

// Webcron endpoint: hosting has no shell/cron access, only URL-based webcron.
// An external service (e.g. cron-job.org) must GET this URL every minute.
Route::get('/cron/schedule-run', function (Request $request) {
    if (! CronToken::isValid($request->query('token'))) {
        abort(403);
    }

    // Vlastný buffer, nie Artisan::output(): naplánované príkazy sa teraz volajú
    // v procese (viď routes/console.php) a každé vnorené Artisan::call() prepíše
    // to, čo Artisan::output() vráti — bez neho by tu ostal výpis posledného
    // príkazu namiesto prehľadu celého behu.
    $output = new BufferedOutput;

    Artisan::call('schedule:run', [], $output);

    // Až po úspešnom behu — nedoručený ping je pre watchdog signál, že webcron
    // vypadol. Viď App\Support\CronHeartbeat.
    CronHeartbeat::ping();

    return response($output->fetch(), 200)->header('Content-Type', 'text/plain');
});

if (! Route::has('sanctum.csrf-cookie')) {
    Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
        ->middleware('web')
        ->name('sanctum.csrf-cookie');
}
