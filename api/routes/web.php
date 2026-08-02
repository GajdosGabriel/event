<?php

use App\Support\CronHeartbeat;
use App\Support\CronToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::get('/login', function () {
    return null;
})->name('login');

// Webcron endpoint: hosting has no shell/cron access, only URL-based webcron.
// An external service (e.g. cron-job.org) must GET this URL every minute.
Route::get('/cron/schedule-run', function (Request $request) {
    if (! CronToken::isValid($request->query('token'))) {
        abort(403);
    }

    Artisan::call('schedule:run');
    $output = Artisan::output();

    // Až po úspešnom behu — nedoručený ping je pre watchdog signál, že webcron
    // vypadol. Viď App\Support\CronHeartbeat.
    CronHeartbeat::ping();

    return response($output, 200)->header('Content-Type', 'text/plain');
});

if (! Route::has('sanctum.csrf-cookie')) {
    Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
        ->middleware('web')
        ->name('sanctum.csrf-cookie');
}
