<?php

use App\Http\Controllers\{HomeController, TestController};
use App\Http\Controllers\Admin\{ CanalController as AdminCanalController, DashboardController as AdminDashboardController, EventController as AdminEventController, FileController as AdminFileController, UserController as AdminUserController, MunicipalityController as AdminMunicipalityController, VenueController as AdminVenueController };
use App\Http\Controllers\Admin\AdminToolsController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardCanalController;
use App\Http\Controllers\Dashboard\DashboardEventController;
use App\Http\Controllers\Dashboard\DashboardFileController;
use App\Http\Controllers\Dashboard\DashboardHomeController;
use App\Http\Controllers\Dashboard\DashboardMunicipalityController;
use App\Http\Controllers\Dashboard\DashboardOrganizationController;
use App\Http\Controllers\Dashboard\DashboardRoleController;
use App\Http\Controllers\Dashboard\DashboardUserController;
use App\Http\Controllers\Dashboard\DashboardVenueController;
use App\Http\Controllers\Dashboard\DashboardTicketController;
use App\Http\Controllers\Dashboard\DashboardTicketTypeController;
use App\Http\Controllers\Public\{CanalController as PublicCanalController, EventController as PublicEventController, MessageController as PublicMessageController, TicketController as PublicTicketController, TicketQrController as PublicTicketQrController, TicketTypeController as PublicTicketTypeController, AdmissionQrController as PublicAdmissionQrController, AttendeeRsvpController as PublicAttendeeRsvpController, VenueController as PublicVenueController, WorkshopRegistrationController as PublicWorkshopRegistrationController};
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Route};
use Illuminate\Support\Facades\Auth;

// if (app()->environment('local') && ! app()->runningUnitTests()) {
//      $user = User::whereKey(1)->first(); // ?? User::first();
//     // $user =  User::first(1);

//     if ($user !== null) {
//         Auth::login($user);
//     }
// }

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return new UserResource($request->user());
});


Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login-form', [AuthController::class, 'loginForm'])->name('auth.loginForm');
Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/login/google', [AuthController::class, 'googleAuth'])->name('auth.login.google');
Route::post('/login/facebook', [AuthController::class, 'facebookAuth'])->name('auth.login.facebook');
Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/register/google', [AuthController::class, 'googleAuth'])->name('auth.register.google');
Route::post('/register/facebook', [AuthController::class, 'facebookAuth'])->name('auth.register.facebook');
Route::post('/register/resend', [AuthController::class, 'resendRegistrationVerification'])->name('auth.register.resend');
Route::post('/register/verify', [AuthController::class, 'verifyRegistration'])->name('auth.register.verify');
Route::get('/register/verify/{token}', [AuthController::class, 'verifyRegistrationLink'])->name('auth.register.verify.link');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');


Route::get('events/municipalities-overview', [PublicEventController::class, 'municipalitiesOverview'])
    ->name('public.events.municipalities.overview');

Route::get('events/{id}/files', [PublicEventController::class, 'files'])->name('public.events.files');
Route::get('events/{event}/ticket-types', [PublicTicketTypeController::class, 'index'])->name('public.events.ticket-types.index');
Route::post('events/{event}/tickets', [PublicTicketController::class, 'store'])->name('public.events.tickets.store');

// Generické „Poslať správu" pre ľubovoľný cieľ (podujatie / miesto / kanál…).
Route::post('messages', [PublicMessageController::class, 'store'])->name('public.messages.store');

// Prihlásenie / odhlásenie prihláseného používateľa na workshop podujatia.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('events/{event}/workshops/{type}', [PublicWorkshopRegistrationController::class, 'store'])
        ->name('public.events.workshops.store');
    Route::delete('events/{event}/workshops/{type}', [PublicWorkshopRegistrationController::class, 'destroy'])
        ->name('public.events.workshops.destroy');

    // Samoobslužné zrušenie vlastnej registrácie na podujatie.
    Route::delete('events/{event}/registration', [PublicTicketController::class, 'cancelOwn'])
        ->name('public.events.registration.destroy');
});
// Potvrdenie účasti účastníkom z e-mailu (chránené tokenom, bez prihlásenia).
Route::get('rsvp/{token}', [PublicAttendeeRsvpController::class, 'show'])->name('public.rsvp.show');
Route::post('rsvp/{token}/confirm', [PublicAttendeeRsvpController::class, 'confirm'])->name('public.rsvp.confirm');
Route::post('rsvp/{token}/decline', [PublicAttendeeRsvpController::class, 'decline'])->name('public.rsvp.decline');

Route::get('tickets/{uuid}', [PublicTicketController::class, 'show'])->name('public.tickets.show');
Route::get('tickets/{uuid}/qr', [PublicTicketQrController::class, 'show'])->name('public.tickets.qr');
Route::get('admissions/{uuid}/qr', [PublicAdmissionQrController::class, 'show'])->name('public.admissions.qr');
Route::get('venues/{id}', [PublicVenueController::class, 'show'])->name('public.venues.show');
Route::get('venues/{id}/events', [PublicVenueController::class, 'events'])->name('public.venues.events');
Route::get('venues/{id}/files', [PublicVenueController::class, 'files'])->name('public.venues.files');

Route::get('canals/{id}/events', [PublicCanalController::class, 'events'])->name('public.canals.events');

Route::apiResources([
    'events' => PublicEventController::class,
    'canals' => PublicCanalController::class,
    'test'   => TestController::class,
]);


Route::prefix('dashboard')->name('dashboard.')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DashboardHomeController::class, 'index'])->name('home');
    Route::get('municipalities/all', [DashboardMunicipalityController::class, 'all']);
    Route::get('canals/municipalities-overview', [DashboardCanalController::class, 'municipalitiesOverview'])
        ->name('canals.municipalities.overview')
        ->middleware('permission:canal.view');

    Route::apiResource('canals', DashboardCanalController::class)
        ->only(['index', 'show'])
        ->middleware('permission:canal.view');
    Route::apiResource('canals', DashboardCanalController::class)
        ->only(['store', 'update'])
        ->middleware('permission:canal.update');
    Route::apiResource('canals', DashboardCanalController::class)
        ->only(['destroy'])
        ->middleware('permission:canal.delete');
    Route::post('canals/{canal}/restore', [DashboardCanalController::class, 'restore'])
        ->name('canals.restore')
        ->middleware('permission:canal.delete');
    Route::get('canals/{canal}/events', [DashboardCanalController::class, 'events'])
        ->name('canals.events')
        ->middleware('permission:canal.view');

    Route::apiResource('files', DashboardFileController::class)
        ->only(['index', 'show'])
        ->middleware('permission:file.view');
    Route::apiResource('files', DashboardFileController::class)
        ->only(['store'])
        ->middleware('permission:file.create');
    Route::apiResource('files', DashboardFileController::class)
        ->only(['update'])
        ->middleware('permission:file.update');
    Route::apiResource('files', DashboardFileController::class)
        ->only(['destroy'])
        ->middleware('permission:file.delete');
    Route::post('files/{id}/restore', [DashboardFileController::class, 'restore'])
        ->name('files.restore')
        ->middleware('permission:file.delete');
    Route::post('files/reorder', [DashboardFileController::class, 'reorder'])
        ->name('files.reorder')
        ->middleware('permission:file.update');

    Route::get('events/municipalities-overview', [DashboardEventController::class, 'municipalitiesOverview'])
        ->name('events.municipalities.overview')
        ->middleware('permission:event.view');
    Route::post('events/detect-from-text', [DashboardEventController::class, 'detectFromText'])
        ->name('events.detect-from-text')
        ->middleware('permission:event.create');
    Route::post('events/improve-text', [DashboardEventController::class, 'improveText'])
        ->name('events.improve-text')
        ->middleware('permission:event.create');

    Route::apiResource('events', DashboardEventController::class)
        ->only(['index', 'show'])
        ->middleware('permission:event.view');
    Route::apiResource('events', DashboardEventController::class)
        ->only(['store'])
        ->middleware('permission:event.create');
    Route::apiResource('events', DashboardEventController::class)
        ->only(['update'])
        ->middleware('permission:event.update');
    Route::post('events/{event}/publish', [DashboardEventController::class, 'publish'])
        ->name('events.publish')
        ->middleware('permission:event.update');
    Route::post('events/{event}/duplicate', [DashboardEventController::class, 'duplicate'])
        ->name('events.duplicate')
        ->middleware('permission:event.create');
    Route::apiResource('events', DashboardEventController::class)
        ->only(['destroy'])
        ->middleware('permission:event.delete');
    Route::post('events/{event}/restore', [DashboardEventController::class, 'restore'])
        ->name('events.restore')
        ->middleware('permission:event.delete');

    // Konfigurácia predaja lístkov pre podujatie (typy + nastavenia).
    Route::get('events/{event}/ticket-types', [DashboardTicketTypeController::class, 'index'])
        ->name('events.ticket-types.index')
        ->middleware('permission:event.view');
    Route::post('events/{event}/ticket-types', [DashboardTicketTypeController::class, 'store'])
        ->name('events.ticket-types.store')
        ->middleware('permission:event.update');
    Route::put('events/{event}/ticket-types/{type}', [DashboardTicketTypeController::class, 'update'])
        ->name('events.ticket-types.update')
        ->middleware('permission:event.update');
    Route::delete('events/{event}/ticket-types/{type}', [DashboardTicketTypeController::class, 'destroy'])
        ->name('events.ticket-types.destroy')
        ->middleware('permission:event.update');
    Route::put('events/{event}/ticketing', [DashboardTicketController::class, 'settings'])
        ->name('events.ticketing.settings')
        ->middleware('permission:event.update');

    Route::get('events/{event}/tickets', [DashboardTicketController::class, 'index'])
        ->name('events.tickets.index')
        ->middleware('permission:ticket.view');
    Route::get('events/{event}/checkin-stats', [DashboardTicketController::class, 'checkinStats'])
        ->name('events.checkin-stats')
        ->middleware('permission:ticket.view');
    Route::post('tickets/checkin', [DashboardTicketController::class, 'checkin'])
        ->name('tickets.checkin')
        ->middleware('permission:ticket.checkin');
    Route::post('tickets/checkin/manual', [DashboardTicketController::class, 'checkinManual'])
        ->name('tickets.checkin.manual')
        ->middleware('permission:ticket.checkin');
    Route::post('tickets/checkin/undo', [DashboardTicketController::class, 'checkinUndo'])
        ->name('tickets.checkin.undo')
        ->middleware('permission:ticket.checkin');
    Route::get('tickets/{id}', [DashboardTicketController::class, 'show'])
        ->name('tickets.show')
        ->middleware('permission:ticket.view');
    Route::post('tickets/{id}', [DashboardTicketController::class, 'update'])
        ->name('tickets.update')
        ->middleware('permission:ticket.update');
    Route::post('tickets/{id}/resend', [DashboardTicketController::class, 'resend'])
        ->name('tickets.resend')
        ->middleware('permission:ticket.update');
    Route::post('admissions/{admission}/cancel', [DashboardTicketController::class, 'cancelAdmission'])
        ->name('admissions.cancel')
        ->middleware('permission:ticket.update');

    Route::apiResource('municipalities', DashboardMunicipalityController::class);

    Route::post('venues/detect', [DashboardVenueController::class, 'detect'])
        ->name('venues.detect')
        ->middleware('permission:venue.create');
    Route::get('venues/municipalities-overview', [DashboardVenueController::class, 'municipalitiesOverview'])
        ->name('venues.municipalities.overview')
        ->middleware('permission:venue.view');
    Route::apiResource('venues', DashboardVenueController::class)
        ->only(['index', 'show'])
        ->middleware('permission:venue.view');
    Route::apiResource('venues', DashboardVenueController::class)
        ->only(['store'])
        ->middleware('permission:venue.create');
    Route::apiResource('venues', DashboardVenueController::class)
        ->only(['update'])
        ->middleware('permission:venue.update');
    Route::apiResource('venues', DashboardVenueController::class)
        ->only(['destroy'])
        ->middleware('permission:venue.delete');
    Route::post('venues/{venue}/restore', [DashboardVenueController::class, 'restore'])
        ->name('venues.restore')
        ->middleware('permission:venue.delete');
    Route::get('venues/{venue}/events', [DashboardVenueController::class, 'events'])
        ->name('venues.events')
        ->middleware('permission:venue.view');
    Route::apiResource('users',  DashboardUserController::class);
    Route::post('users/{user}/restore', [DashboardUserController::class, 'restore'])->name('users.restore');
    Route::post('users/active-canal', [DashboardUserController::class, 'setActiveCanal']);

    Route::apiResource('organizations', DashboardOrganizationController::class)
        ->only(['index', 'show'])
        ->middleware('permission:organization.view');
    Route::apiResource('organizations', DashboardOrganizationController::class)
        ->only(['store'])
        ->middleware('permission:organization.create');
    Route::apiResource('organizations', DashboardOrganizationController::class)
        ->only(['update'])
        ->middleware('permission:organization.update');
    Route::apiResource('organizations', DashboardOrganizationController::class)
        ->only(['destroy'])
        ->middleware('permission:organization.delete');
    Route::post('organizations/{organization}/restore', [DashboardOrganizationController::class, 'restore'])
        ->name('organizations.restore')
        ->middleware('permission:organization.delete');

    Route::get('roles', [DashboardRoleController::class, 'roles'])->name('roles.index');
    Route::get('permissions', [DashboardRoleController::class, 'permissions'])->name('permissions.index');
    Route::put('users/{user}/roles', [DashboardRoleController::class, 'syncUserRoles'])->name('users.roles.sync');
})->middleware('auth:sanctum');

Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'role:super-admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('home');
    Route::get('municipalities/all', [AdminMunicipalityController::class, 'all']);
    Route::get('canals/municipalities-overview', [AdminCanalController::class, 'municipalitiesOverview'])
        ->name('canals.municipalities.overview')
        ->middleware('permission:canal.view');
    Route::get('files', [AdminFileController::class, 'index'])
        ->name('files.index');
    Route::delete('files/{id}', [AdminFileController::class, 'destroy'])
        ->name('files.destroy')
        ->middleware('permission:file.delete');
    Route::post('files/{id}/restore', [AdminFileController::class, 'restore'])
        ->name('files.restore')
        ->middleware('permission:file.delete');

    Route::apiResource('canals', AdminCanalController::class)
        ->only(['index', 'show'])
        ->middleware('permission:canal.view');
    Route::apiResource('canals', AdminCanalController::class)
        ->only(['store', 'update'])
        ->middleware('permission:canal.update');
    Route::post('canals/{canal}/restore', [AdminCanalController::class, 'restore'])
        ->name('canals.restore')
        ->middleware('permission:canal.delete');
    Route::get('canals/{canal}/events', [AdminCanalController::class, 'events'])
        ->name('canals.events')
        ->middleware('permission:canal.view');

    Route::get('events/municipalities-overview', [AdminEventController::class, 'municipalitiesOverview'])
        ->name('events.municipalities.overview')
        ->middleware('permission:event.view');
    Route::post('events/improve-text', [AdminEventController::class, 'improveText'])
        ->name('events.improve-text')
        ->middleware('permission:event.update');

    Route::apiResource('events', AdminEventController::class)
        ->only(['index', 'show'])
        ->middleware('permission:event.view');
    Route::apiResource('events', AdminEventController::class)
        ->only(['store'])
        ->middleware('permission:event.create');
    Route::apiResource('events', AdminEventController::class)
        ->only(['update'])
        ->middleware('permission:event.update');
    Route::post('events/{event}/publish', [AdminEventController::class, 'publish'])
        ->name('events.publish')
        ->middleware('permission:event.update');
    Route::post('events/{event}/duplicate', [AdminEventController::class, 'duplicate'])
        ->name('events.duplicate')
        ->middleware('permission:event.create');
    Route::post('events/{event}/restore', [AdminEventController::class, 'restore'])
        ->name('events.restore')
        ->middleware('permission:event.delete');

    Route::post('tools/import-events', [AdminToolsController::class, 'runImportEvents'])->name('tools.import-events');
    Route::get('tools/import-events/runs/{runId}', [AdminToolsController::class, 'importRunStatus'])->name('tools.import-events.status');
    Route::post('tools/ai-detector', [AdminToolsController::class, 'runAiDetector'])->name('tools.ai-detector');
    Route::post('tools/archive-events', [AdminToolsController::class, 'runArchiveEvents'])->name('tools.archive-events');

    Route::apiResource('users', AdminUserController::class)
        ->only(['index', 'show'])
        ->middleware('permission:user.view');
    Route::apiResource('users', AdminUserController::class)
        ->only(['update'])
        ->middleware('permission:user.update');
    Route::apiResource('users', AdminUserController::class)
        ->only(['destroy'])
        ->middleware('permission:user.delete');
    Route::post('users/{user}/restore', [AdminUserController::class, 'restore'])
        ->name('users.restore')
        ->middleware('permission:user.delete');
    Route::apiResource('organizations', AdminOrganizationController::class)
        ->only(['index', 'show'])
        ->middleware('permission:organization.view');
    Route::apiResource('organizations', AdminOrganizationController::class)
        ->only(['store'])
        ->middleware('permission:organization.create');
    Route::apiResource('organizations', AdminOrganizationController::class)
        ->only(['update'])
        ->middleware('permission:organization.update');
    Route::apiResource('organizations', AdminOrganizationController::class)
        ->only(['destroy'])
        ->middleware('permission:organization.delete');
    Route::post('organizations/{organization}/restore', [AdminOrganizationController::class, 'restore'])
        ->name('organizations.restore')
        ->middleware('permission:organization.delete');

    Route::middleware('role:super-admin')->group(function () {
        Route::get('roles', [AdminRoleController::class, 'roles'])->name('roles.index');
        Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('permissions.index');
        Route::put('users/{user}/roles', [AdminRoleController::class, 'syncUserRoles'])->name('users.roles.sync');
    });

    Route::apiResource('municipalities', AdminMunicipalityController::class);

    Route::post('venues/detect', [AdminVenueController::class, 'detect'])
        ->name('venues.detect')
        ->middleware('permission:venue.create');
    Route::get('venues/municipalities-overview', [AdminVenueController::class, 'municipalitiesOverview'])
        ->name('venues.municipalities.overview')
        ->middleware('permission:venue.view');
    Route::apiResource('venues', AdminVenueController::class)
        ->only(['index', 'show'])
        ->middleware('permission:venue.view');
    Route::post('venues/{venue}/restore', [AdminVenueController::class, 'restore'])
        ->name('venues.restore');
    Route::get('venues/{venue}/events', [AdminVenueController::class, 'events'])
        ->name('venues.events')
        ->middleware('permission:venue.delete');
});



Route::get('artisan/run', function () {
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    Artisan::call('config:clear');
    Artisan::call('optimize:clear');
    Artisan::call('queue:work');

    // dd("All is cleared");
});
