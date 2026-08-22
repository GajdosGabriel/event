<?php

use App\Http\Controllers\Admin\AdminToolsController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\CanalController as AdminCanalController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\FileController as AdminFileController;
use App\Http\Controllers\Admin\MunicipalityController as AdminMunicipalityController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\TagSuggestionController as AdminTagSuggestionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardAttendeeController;
use App\Http\Controllers\Dashboard\DashboardCanalController;
use App\Http\Controllers\Dashboard\DashboardCanalTeamController;
use App\Http\Controllers\Dashboard\DashboardEventController;
use App\Http\Controllers\Dashboard\DashboardFileController;
use App\Http\Controllers\Dashboard\DashboardHomeController;
use App\Http\Controllers\Dashboard\DashboardMessageController;
use App\Http\Controllers\Dashboard\DashboardMunicipalityController;
use App\Http\Controllers\Dashboard\DashboardOrganizationController;
use App\Http\Controllers\Dashboard\DashboardQuestionController;
use App\Http\Controllers\Dashboard\DashboardRoleController;
use App\Http\Controllers\Dashboard\DashboardTicketController;
use App\Http\Controllers\Dashboard\DashboardTicketTypeController;
use App\Http\Controllers\Dashboard\DashboardUserController;
use App\Http\Controllers\Dashboard\DashboardVenueController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Public\AdmissionQrController as PublicAdmissionQrController;
use App\Http\Controllers\Public\AnnouncementController as PublicAnnouncementController;
use App\Http\Controllers\Public\AttendeeRsvpController as PublicAttendeeRsvpController;
use App\Http\Controllers\Public\BrokenLinkReportController;
use App\Http\Controllers\Public\CanalController as PublicCanalController;
use App\Http\Controllers\Public\CanalInvitationController as PublicCanalInvitationController;
use App\Http\Controllers\Public\EventCalendarController as PublicEventCalendarController;
use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\Public\EventQuestionController as PublicEventQuestionController;
use App\Http\Controllers\Public\MessageController as PublicMessageController;
use App\Http\Controllers\Public\MunicipalityController as PublicMunicipalityController;
use App\Http\Controllers\Public\PosterController as PublicPosterController;
use App\Http\Controllers\Public\PrerenderController;
use App\Http\Controllers\Public\QuestionBoardController as PublicQuestionBoardController;
use App\Http\Controllers\Public\QuestionController as PublicQuestionController;
use App\Http\Controllers\Public\QuestionSlideController as PublicQuestionSlideController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\SubscriptionController as PublicSubscriptionController;
use App\Http\Controllers\Public\TagController as PublicTagController;
use App\Http\Controllers\Public\TicketController as PublicTicketController;
use App\Http\Controllers\Public\TicketQrController as PublicTicketQrController;
use App\Http\Controllers\Public\TicketTypeController as PublicTicketTypeController;
use App\Http\Controllers\Public\VenueController as PublicVenueController;
use App\Http\Controllers\Public\WorkshopRegistrationController as PublicWorkshopRegistrationController;
use App\Http\Controllers\Webhooks\AccountWebhookController;
use App\Http\Resources\UserResource;
use App\Support\CronToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return new UserResource($request->user());
});

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login-form', [AuthController::class, 'loginForm'])->name('auth.loginForm');

// Prihlásenie: prísny limit proti skúšaniu hesiel.
Route::middleware('throttle:auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/login/google', [AuthController::class, 'googleAuth'])->name('auth.login.google');
    Route::post('/login/facebook', [AuthController::class, 'facebookAuth'])->name('auth.login.facebook');
});

// Registrácia: zakladá účty a posiela e-maily, preto vlastný limit.
Route::middleware('throttle:register')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/register/google', [AuthController::class, 'googleAuth'])->name('auth.register.google');
    Route::post('/register/facebook', [AuthController::class, 'facebookAuth'])->name('auth.register.facebook');
    Route::post('/register/resend', [AuthController::class, 'resendRegistrationVerification'])->name('auth.register.resend');
    Route::post('/register/verify', [AuthController::class, 'verifyRegistration'])->name('auth.register.verify');
});

Route::get('/register/verify/{token}', [AuthController::class, 'verifyRegistrationLink'])->name('auth.register.verify.link');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');

// Dosah pre vyhľadávače a zdieľanie. Obe routy vracajú HTML/XML, nie JSON —
// Apache na ne prepisuje `/sitemap.xml` a požiadavky crawlerov (pozri
// `deploy/htaccess.md`). Limiter chráni generovanie pred volaním v slučke;
// samotná odpoveď je cachovaná (sitemap 1 h, prerender 5 min).
Route::get('sitemap.xml', SitemapController::class)
    ->name('public.sitemap')
    ->middleware('throttle:60,1');
Route::get('prerender', PrerenderController::class)
    ->name('public.prerender')
    ->middleware('throttle:120,1');

Route::get('events/municipalities-overview', [PublicEventController::class, 'municipalitiesOverview'])
    ->name('public.events.municipalities.overview');

// Číselník obsahových štítkov pre verejný filter (?tags=koncert,folklor).
Route::get('tags', [PublicTagController::class, 'index'])->name('public.tags.index');

// Oznamy a bannery vo verejnom layoute (?placement=top|bottom).
Route::get('announcements', [PublicAnnouncementController::class, 'index'])
    ->name('public.announcements.index');

Route::get('events/{id}/files', [PublicEventController::class, 'files'])->name('public.events.files');

// Podujatie ako `.ics` — odkaz „Pridať do kalendára" v e-maile o lístku.
// Prípona je súčasťou cesty, aby ju kalendáre a mobilné systémy poznali už
// podľa URL.
Route::get('events/{id}/calendar.ics', [PublicEventCalendarController::class, 'show'])
    ->name('public.events.calendar');

Route::get('events/{event}/ticket-types', [PublicTicketTypeController::class, 'index'])->name('public.events.ticket-types.index');
Route::post('events/{event}/tickets', [PublicTicketController::class, 'store'])
    ->name('public.events.tickets.store')
    ->middleware('throttle:public-write');

// „Nahrajte plagát, o všetko ostatné sa postaráme" — analýza beží bez účtu,
// registráciu pýtame až v `claim`, keď človek vidí, čo z plagátu vzniklo.
// `analyze` má limiter `ai`, lebo každé volanie ide do OpenAI a stojí peniaze.
// Číselník obcí pre výber mesta v sprievodcovi — človek pri ňom ešte nemá účet,
// takže dashboardová `municipalities/all` sa použiť nedá.
Route::get('municipalities', [PublicMunicipalityController::class, 'index'])
    ->name('public.municipalities.index');
Route::get('municipalities/{slug}', [PublicMunicipalityController::class, 'show'])
    ->name('public.municipalities.show');
Route::post('poster/analyze', [PublicPosterController::class, 'analyze'])
    ->name('public.poster.analyze')
    ->middleware('throttle:ai');
Route::get('poster/drafts/{draft}', [PublicPosterController::class, 'show'])
    ->name('public.poster.drafts.show');
Route::post('poster/drafts/{draft}/remember', [PublicPosterController::class, 'remember'])
    ->name('public.poster.drafts.remember')
    ->middleware('throttle:public-write');
Route::post('poster/drafts/{draft}/claim', [PublicPosterController::class, 'claim'])
    ->name('public.poster.drafts.claim')
    ->middleware(['auth:sanctum', 'throttle:public-write']);

// Otázky a odpovede na verejnom detaile podujatia. Tá istá nástenka ako
// `/q/{token}` nižšie, ale hľadá sa cez podujatie — token je autorizácia, dá sa
// rotovať a nemá sa šíriť mimo QR, takže ho verejný detail nikdy nedostane.
Route::get('events/{event}/questions', [PublicEventQuestionController::class, 'index'])
    ->name('public.events.questions.index');
Route::post('events/{event}/questions', [PublicEventQuestionController::class, 'store'])
    ->name('public.events.questions.store')
    ->middleware('throttle:questions');

// „Pripomeň mi" — odber podujatia bez účtu. Vzniklo preto, že na bezplatnom
// podujatí bez lístkov sa na verejnom detaile nedá spraviť vôbec nič.
// `ticket` vydá podpísanú známku, že sa formulár naozaj otvoril (SubmissionTicket);
// bez nej POST neprejde, takže bot, ktorý našiel adresu, ju nemá odkiaľ vziať.
Route::get('events/{event}/subscription', [PublicSubscriptionController::class, 'ticket'])
    ->name('public.events.subscription.ticket')
    ->middleware('throttle:public-write');
Route::post('events/{event}/subscription', [PublicSubscriptionController::class, 'store'])
    ->name('public.events.subscription.store')
    ->middleware('throttle:public-write');

// Odhlásenie z pätičky e-mailu — token v odkaze JE autorizácia (ako RSVP nižšie).
Route::get('subscriptions/{token}', [PublicSubscriptionController::class, 'show'])
    ->name('public.subscriptions.show');
Route::delete('subscriptions/{token}', [PublicSubscriptionController::class, 'destroy'])
    ->name('public.subscriptions.destroy')
    ->middleware('throttle:public-write');

// Generické „Poslať správu" pre ľubovoľný cieľ (podujatie / miesto / kanál…).
Route::post('messages', [PublicMessageController::class, 'store'])
    ->name('public.messages.store')
    ->middleware('throttle:messages');

// Beacon pri kliknutí na odkaz mimo portál — nechá hodnotu prednostne overiť.
// Neposiela sa žiadna adresa, len typ a id záznamu (viď controller).
Route::post('link-reports', [BrokenLinkReportController::class, 'store'])
    ->name('public.link-reports.store')
    ->middleware('throttle:public-write');

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
Route::post('rsvp/{token}/confirm', [PublicAttendeeRsvpController::class, 'confirm'])
    ->name('public.rsvp.confirm')
    ->middleware('throttle:public-write');
Route::post('rsvp/{token}/decline', [PublicAttendeeRsvpController::class, 'decline'])
    ->name('public.rsvp.decline')
    ->middleware('throttle:public-write');

// Otázky z publika. Adresa `/q/{token}` sa premieta na plátno a ľudia si ju
// prepisujú rukou, preto je taká krátka; token v nej je autorizáciou (rovnaká
// konvencia ako RSVP vyššie). Vkladá anonym bez účtu — ochrana je vrstvená,
// popis je v App\Services\Questions\QuestionSubmitter.
Route::get('q/{token}', [PublicQuestionBoardController::class, 'show'])
    ->name('public.questions.show');
// Polling namiesto websocketu: hosting nemá shell ani démona (fronta beží cez
// webcron), takže trvalé spojenie nemá kto obsluhovať. Limit je vyšší než
// `public-write`, lebo v sále sa každých pár sekúnd pýta celá miestnosť.
Route::get('q/{token}/stream', [PublicQuestionBoardController::class, 'stream'])
    ->name('public.questions.stream')
    ->middleware('throttle:120,1');
Route::post('q/{token}/questions', [PublicQuestionController::class, 'store'])
    ->name('public.questions.store')
    ->middleware('throttle:questions');
Route::post('q/{token}/questions/{question}/vote', [PublicQuestionController::class, 'vote'])
    ->name('public.questions.vote')
    ->middleware('throttle:public-write');
Route::delete('q/{token}/questions/{question}/vote', [PublicQuestionController::class, 'unvote'])
    ->name('public.questions.unvote')
    ->middleware('throttle:public-write');
// Snímka na plátno. Prípona je súčasťou cesty, aby prehliadač aj PowerPoint
// poznali typ už podľa adresy — rovnako ako pri `calendar.ics`.
Route::get('q/{token}/slide.png', [PublicQuestionSlideController::class, 'png'])
    ->name('public.questions.slide.png')
    ->middleware('throttle:render');
// Samotný QR kód do rohu premietacej steny. Vlastný endpoint, lebo zmenšená
// snímka by v tej veľkosti bola nenaskenovateľná miniatúra.
Route::get('q/{token}/qr.png', [PublicQuestionSlideController::class, 'qr'])
    ->name('public.questions.qr')
    ->middleware('throttle:render');
Route::get('q/{token}/slide.pptx', [PublicQuestionSlideController::class, 'pptx'])
    ->name('public.questions.slide.pptx')
    ->middleware('throttle:render');

// Pozvánka do tímu kanála z e-mailu. Detail je verejný (autorizuje token
// v odkaze), prijatie vyžaduje prihlásený účet s rovnakou adresou.
Route::get('invitations/{token}', [PublicCanalInvitationController::class, 'show'])
    ->name('public.invitations.show');
Route::post('invitations/{token}/accept', [PublicCanalInvitationController::class, 'accept'])
    ->name('public.invitations.accept')
    ->middleware(['auth:sanctum', 'throttle:public-write']);

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
]);

Route::prefix('dashboard')->name('dashboard.')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DashboardHomeController::class, 'index'])->name('home');
    Route::get('municipalities/all', [DashboardMunicipalityController::class, 'all']);
    Route::get('canals/municipalities-overview', [DashboardCanalController::class, 'municipalitiesOverview'])
        ->name('canals.municipalities.overview')
        ->middleware('permission:canal.view');
    Route::get('canals/identity-modes', [DashboardCanalController::class, 'identityModes'])
        ->name('canals.identity-modes')
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
    Route::post('canals/{canal}/publish', [DashboardCanalController::class, 'publish'])
        ->name('canals.publish')
        ->middleware('permission:canal.update');
    Route::post('canals/{canal}/restore', [DashboardCanalController::class, 'restore'])
        ->name('canals.restore')
        ->middleware('permission:canal.delete');
    Route::get('canals/{canal}/events', [DashboardCanalController::class, 'events'])
        ->name('canals.events')
        ->middleware('permission:canal.view');

    // Tím kanála. Bez `permission:` middlewaru — právo je per kanál a rieši ho
    // CanalPolicy (viewTeam / manageTeam), nie globálna rola používateľa.
    Route::get('canals/{canal}/team', [DashboardCanalTeamController::class, 'index'])
        ->name('canals.team.index');
    Route::post('canals/{canal}/team/invitations', [DashboardCanalTeamController::class, 'invite'])
        ->name('canals.team.invite')
        ->middleware('throttle:messages');
    Route::post('canals/{canal}/team/invitations/{invitation}/resend', [DashboardCanalTeamController::class, 'resendInvitation'])
        ->name('canals.team.invitations.resend')
        ->middleware('throttle:messages');
    Route::delete('canals/{canal}/team/invitations/{invitation}', [DashboardCanalTeamController::class, 'destroyInvitation'])
        ->name('canals.team.invitations.destroy');
    Route::put('canals/{canal}/team/{user}', [DashboardCanalTeamController::class, 'updateRole'])
        ->name('canals.team.update');
    Route::delete('canals/{canal}/team/{user}', [DashboardCanalTeamController::class, 'destroy'])
        ->name('canals.team.destroy');

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
        ->middleware(['permission:event.create', 'throttle:ai']);
    Route::post('events/improve-text', [DashboardEventController::class, 'improveText'])
        ->name('events.improve-text')
        ->middleware(['permission:event.create', 'throttle:ai']);

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
    Route::post('events/{event}/unarchive', [DashboardEventController::class, 'unarchive'])
        ->name('events.unarchive')
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

    // Nástenky otázok. `event.view` stačí na prezeranie, všetko ostatné (vrátane
    // moderovania) je `event.update` — schválená otázka sa objaví na plátne
    // pred sálou, takže je to publikovanie v mene organizátora.
    Route::get('events/{event}/question-boards', [DashboardQuestionController::class, 'index'])
        ->name('events.question-boards.index')
        ->middleware('permission:event.view');
    Route::post('events/{event}/question-boards', [DashboardQuestionController::class, 'store'])
        ->name('events.question-boards.store')
        ->middleware('permission:event.update');
    Route::put('question-boards/{board}', [DashboardQuestionController::class, 'update'])
        ->name('question-boards.update')
        ->middleware('permission:event.update');
    Route::post('question-boards/{board}/rotate-token', [DashboardQuestionController::class, 'rotateToken'])
        ->name('question-boards.rotate-token')
        ->middleware('permission:event.update');
    Route::get('question-boards/{board}/questions', [DashboardQuestionController::class, 'questions'])
        ->name('question-boards.questions')
        ->middleware('permission:event.view');
    Route::patch('questions/{question}', [DashboardQuestionController::class, 'updateQuestion'])
        ->name('questions.update')
        ->middleware('permission:event.update');
    Route::delete('questions/{question}', [DashboardQuestionController::class, 'destroyQuestion'])
        ->name('questions.destroy')
        ->middleware('permission:event.update');

    Route::get('events/{event}/tickets', [DashboardTicketController::class, 'index'])
        ->name('events.tickets.index')
        ->middleware('permission:ticket.view');
    Route::get('events/{event}/checkin-stats', [DashboardTicketController::class, 'checkinStats'])
        ->name('events.checkin-stats')
        ->middleware('permission:ticket.view');

    // Zoznam prihlásených: export a hromadný e-mail. Export má právo `ticket.view`
    // (je to čítanie zoznamu), rozposlanie `event.update` — je to komunikácia
    // v mene organizátora, na to obsluha vstupu nestačí.
    Route::get('events/{event}/attendees/export', [DashboardAttendeeController::class, 'export'])
        ->name('events.attendees.export')
        ->middleware('permission:ticket.view');
    Route::get('events/{event}/attendees/recipients', [DashboardAttendeeController::class, 'recipientCount'])
        ->name('events.attendees.recipients')
        ->middleware('permission:ticket.view');
    Route::post('events/{event}/attendees/email', [DashboardAttendeeController::class, 'email'])
        ->name('events.attendees.email')
        ->middleware(['permission:event.update', 'throttle:messages']);
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

    // Inbox správ. Bez `permission:` middlewaru — inbox je osobný, právo drží
    // MessagePolicy podľa recipient_user_id, nie globálna rola.
    Route::get('messages', [DashboardMessageController::class, 'index'])->name('messages.index');
    Route::get('messages/unread-count', [DashboardMessageController::class, 'unreadCount'])
        ->name('messages.unread-count');
    Route::get('messages/{message}', [DashboardMessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{message}/read', [DashboardMessageController::class, 'markRead'])
        ->name('messages.read');
    Route::post('messages/{message}/reply', [DashboardMessageController::class, 'reply'])
        ->name('messages.reply')
        ->middleware('throttle:messages');

    Route::apiResource('municipalities', DashboardMunicipalityController::class);

    Route::post('venues/detect', [DashboardVenueController::class, 'detect'])
        ->name('venues.detect')
        ->middleware(['permission:venue.create', 'throttle:ai']);
    Route::post('venues/geocode', [DashboardVenueController::class, 'geocode'])
        ->name('venues.geocode')
        ->middleware(['permission:venue.view', 'throttle:60,1']);
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
    Route::post('venues/{venue}/publish', [DashboardVenueController::class, 'publish'])
        ->name('venues.publish')
        ->middleware('permission:venue.update');
    Route::post('venues/{venue}/restore', [DashboardVenueController::class, 'restore'])
        ->name('venues.restore')
        ->middleware('permission:venue.delete');
    Route::get('venues/{venue}/events', [DashboardVenueController::class, 'events'])
        ->name('venues.events')
        ->middleware('permission:venue.view');
    Route::apiResource('users', DashboardUserController::class);
    Route::post('users/{user}/restore', [DashboardUserController::class, 'restore'])->name('users.restore');
    Route::post('users/active-canal', [DashboardUserController::class, 'setActiveCanal']);

    // Musí byť pred apiResource, inak by `{organization}` pohltilo `lookup-ico`.
    Route::post('organizations/lookup-ico', [DashboardOrganizationController::class, 'lookupIco'])
        ->name('organizations.lookup-ico')
        ->middleware(['permission:organization.create', 'throttle:6,1']);
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
    // Väzba firma ↔ kanál. Členov nespravuje organizácia, ale tím kanála
    // (`canals/{canal}/team`) — rola platí vždy len v konkrétnom kanáli.
    Route::post('organizations/{organization}/canals', [DashboardOrganizationController::class, 'attachCanal'])
        ->name('organizations.canals.store')
        ->middleware('permission:organization.update');
    Route::delete('organizations/{organization}/canals/{canal}', [DashboardOrganizationController::class, 'detachCanal'])
        ->name('organizations.canals.destroy')
        ->middleware('permission:organization.update');

    Route::get('roles', [DashboardRoleController::class, 'roles'])->name('roles.index');
    Route::get('permissions', [DashboardRoleController::class, 'permissions'])->name('permissions.index');
    Route::put('users/{user}/roles', [DashboardRoleController::class, 'syncUserRoles'])->name('users.roles.sync');
})->middleware('auth:sanctum');

Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'role:super-admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('home');
    Route::get('municipalities/all', [AdminMunicipalityController::class, 'all']);
    // Podklad na rozšírenie číselníka štítkov (číselník sám je v TagSeeder-i).
    Route::get('tag-suggestions', [AdminTagSuggestionController::class, 'index'])->name('tag-suggestions.index');
    Route::patch('tag-suggestions/{tagSuggestion}', [AdminTagSuggestionController::class, 'update'])->name('tag-suggestions.update');
    Route::get('canals/municipalities-overview', [AdminCanalController::class, 'municipalitiesOverview'])
        ->name('canals.municipalities.overview')
        ->middleware('permission:canal.view');
    Route::get('canals/identity-modes', [AdminCanalController::class, 'identityModes'])
        ->name('canals.identity-modes')
        ->middleware('permission:canal.view');
    Route::get('files', [AdminFileController::class, 'index'])
        ->name('files.index');
    Route::delete('files/{id}', [AdminFileController::class, 'destroy'])
        ->name('files.destroy')
        ->middleware('permission:file.delete');
    Route::delete('files/{id}/force', [AdminFileController::class, 'forceDestroy'])
        ->name('files.force-destroy')
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
    Route::post('canals/{canal}/publish', [AdminCanalController::class, 'publish'])
        ->name('canals.publish')
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
        ->middleware(['permission:event.update', 'throttle:ai']);

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
    Route::post('events/{event}/unarchive', [AdminEventController::class, 'unarchive'])
        ->name('events.unarchive')
        ->middleware('permission:event.update');
    Route::post('events/{event}/duplicate', [AdminEventController::class, 'duplicate'])
        ->name('events.duplicate')
        ->middleware('permission:event.create');
    Route::post('events/{event}/restore', [AdminEventController::class, 'restore'])
        ->name('events.restore')
        ->middleware('permission:event.delete');

    Route::post('tools/import-events', [AdminToolsController::class, 'runImportEvents'])->name('tools.import-events');
    Route::get('tools/import-events/runs/{runId}', [AdminToolsController::class, 'importRunStatus'])->name('tools.import-events.status');
    Route::post('tools/ai-detector', [AdminToolsController::class, 'runAiDetector'])
        ->name('tools.ai-detector')
        ->middleware('throttle:ai');
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
    // Musí byť pred apiResource, inak by `{organization}` pohltilo `lookup-ico`.
    Route::post('organizations/lookup-ico', [AdminOrganizationController::class, 'lookupIco'])
        ->name('organizations.lookup-ico')
        ->middleware(['permission:organization.create', 'throttle:6,1']);
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
    // Väzba firma ↔ kanál; členov spravuje tím kanála, nie organizácia.
    Route::post('organizations/{organization}/canals', [AdminOrganizationController::class, 'attachCanal'])
        ->name('organizations.canals.store')
        ->middleware('permission:organization.update');
    Route::delete('organizations/{organization}/canals/{canal}', [AdminOrganizationController::class, 'detachCanal'])
        ->name('organizations.canals.destroy')
        ->middleware('permission:organization.update');

    Route::middleware('role:super-admin')->group(function () {
        Route::get('roles', [AdminRoleController::class, 'roles'])->name('roles.index');
        Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('permissions.index');
        Route::put('users/{user}/roles', [AdminRoleController::class, 'syncUserRoles'])->name('users.roles.sync');
    });

    Route::apiResource('municipalities', AdminMunicipalityController::class);

    // Oznamy a bannery verejného layoutu — celá skupina je za `role:super-admin`.
    Route::apiResource('announcements', AdminAnnouncementController::class);

    Route::post('venues/detect', [AdminVenueController::class, 'detect'])
        ->name('venues.detect')
        ->middleware(['permission:venue.create', 'throttle:ai']);
    Route::post('venues/geocode', [AdminVenueController::class, 'geocode'])
        ->name('venues.geocode')
        ->middleware(['permission:venue.view', 'throttle:60,1']);
    Route::get('venues/municipalities-overview', [AdminVenueController::class, 'municipalitiesOverview'])
        ->name('venues.municipalities.overview')
        ->middleware('permission:venue.view');
    Route::apiResource('venues', AdminVenueController::class)
        ->only(['index', 'show'])
        ->middleware('permission:venue.view');
    // Admin UI má /admin/venues/:id/edit už dávno, zapisovacie routy mu ale
    // chýbali — front preto tajne posielal na /dashboard/venues, kde admin na
    // cudzom mieste narazil na vlastníctvo cez kanál.
    Route::apiResource('venues', AdminVenueController::class)
        ->only(['store'])
        ->middleware('permission:venue.create');
    Route::apiResource('venues', AdminVenueController::class)
        ->only(['update'])
        ->middleware('permission:venue.update');
    Route::apiResource('venues', AdminVenueController::class)
        ->only(['destroy'])
        ->middleware('permission:venue.delete');
    Route::post('venues/{venue}/publish', [AdminVenueController::class, 'publish'])
        ->name('venues.publish')
        ->middleware('permission:venue.update');
    Route::post('venues/{venue}/restore', [AdminVenueController::class, 'restore'])
        ->name('venues.restore');
    Route::get('venues/{venue}/events', [AdminVenueController::class, 'events'])
        ->name('venues.events')
        ->middleware('permission:venue.delete');
});

// Webhooky z Accountu. Autentizáciu rieši HMAC podpis v hlavičke, nie session —
// volá ich server Accountu, nie prehliadač.
Route::post('webhooks/account', AccountWebhookController::class)
    ->middleware('throttle:ops')
    ->name('webhooks.account');

// Po-deploy vyčistenie cache. Hosting nemá shell, preto sa spúšťa cez URL —
// rovnako ako webcron chránené tokenom z CRON_SECRET.
//
// Pozor: zámerne tu nie je queue:work. Ten beží ako blokujúci daemon a v HTTP
// requeste by držal PHP worker až do timeoutu.
Route::get('artisan/run', function (Request $request) {
    if (! CronToken::isValid($request->query('token'))) {
        abort(403);
    }

    Artisan::call('optimize:clear');

    return response()->json(['status' => 'ok', 'output' => Artisan::output()]);
})->middleware('throttle:ops')->name('artisan.run');
