<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::get('/login', function () {
    return  null;
})->name('login');

// Webcron endpoint: hosting has no shell/cron access, only URL-based webcron.
// An external service (e.g. cron-job.org) must GET this URL every minute.
Route::get('/cron/schedule-run', function (Request $request) {
    if ($request->query('token') !== config('app.cron_secret')) {
        abort(403);
    }

    Artisan::call('schedule:run');

    return response(Artisan::output(), 200)->header('Content-Type', 'text/plain');
});

if (! Route::has('sanctum.csrf-cookie')) {
    Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
        ->middleware('web')
        ->name('sanctum.csrf-cookie');
}
