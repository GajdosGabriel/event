<?php

use App\Http\Middleware\LogLastUserActivity;
use App\Http\Middleware\SetLocale;
use App\Listeners\SystemLogSubscriber;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/*
 * Premenné z .env len do $_ENV/$_SERVER, nie do procesu cez putenv().
 *
 * Apache na Windows (mpm_winnt) beží ako jeden proces s vláknami a putenv()
 * zapisuje do prostredia celého procesu — teda aj do súbežných požiadaviek
 * iných aplikácií na tom istom serveri. Event volá Account (napr. vyhľadanie
 * IČO) vnorenou HTTP požiadavkou, takže Account sa štartoval s DB_DATABASE
 * Eventu a padal na „Table 'event-api.service_clients' doesn't exist“.
 * Superglobály sú na rozdiel od prostredia procesu viazané na požiadavku.
 */
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Locale sa nastavuje ako prvé — validačné hlášky aj chyby zo súbežných
        // middlewarov už majú byť v jazyku, ktorý si klient vypýtal.
        $middleware->prepend(SetLocale::class);
        $middleware->append(LogLastUserActivity::class);
        // Základný strop na celé API. Citlivé endpointy majú navyše vlastný
        // prísnejší limiter — limity sú definované v AppServiceProvider.
        $middleware->throttleApi();
        $middleware->statefulApi();
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Všetko pod /api je JSON API. Bez tohto Laravel pri chýbajúcej hlavičke
        // Accept: application/json vráti HTML chybovú stránku a SPA dostane
        // namiesto chyby kus HTML, ktorý nevie spracovať.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api', 'api/*') || $request->expectsJson(),
        );

        // Mail, ktorý neodišiel, patrí aj do denníka udalostí (admin → Denník),
        // nielen do súborového logu. Hlásenie sa tým nezastaví.
        $exceptions->report(function (TransportExceptionInterface $e) {
            SystemLogSubscriber::mailFailed($e);
        });

        // Texty chýb idú z lang/*/errors.php — SetLocale beží ako prvý middleware,
        // takže sú v jazyku klienta. Handler výnimku pred callbackmi prebalí
        // (prepareException), preto sa tu chytajú už HTTP výnimky.
        $wantsJson = fn (Request $request) => $request->is('api', 'api/*') || $request->expectsJson();

        // Model binding zlyhá ako ModelNotFoundException; navonok je to 404
        // s rovnakým tvarom ako ostatné chyby, nie „no query results for model".
        // To isté pre holé abort(404) bez textu.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($wantsJson) {
            if ($wantsJson($request) && ($e->getPrevious() instanceof ModelNotFoundException || $e->getMessage() === '')) {
                return response()->json(['message' => __('errors.not_found')], 404);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request)
                ? response()->json(['message' => __('errors.unauthenticated')], 401)
                : null;
        });

        // `permission:` / `role:` middleware (spatie) hlási po anglicky.
        $exceptions->render(function (UnauthorizedException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request)
                ? response()->json(['message' => __('errors.forbidden')], $e->getStatusCode())
                : null;
        });

        // Policy bez vlastnej hlášky. Vlastný text z Response::deny() ostáva.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($wantsJson) {
            if ($wantsJson($request) && $e->getMessage() === 'This action is unauthorized.') {
                return response()->json(['message' => __('errors.forbidden')], 403);
            }

            return null;
        });

        // Nečakaná chyba (SQL, TypeError…): používateľ nemá vidieť text výnimky.
        // Pri APP_DEBUG ostáva detail v poli `debug` pre vývojára.
        $exceptions->render(function (Throwable $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)
                || $e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpResponseException) {
                return null;
            }

            $payload = ['message' => __('errors.server')];

            if (config('app.debug')) {
                $payload['debug'] = [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            return response()->json($payload, 500);
        });
    })->create();
