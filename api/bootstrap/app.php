<?php

use App\Http\Middleware\LogLastUserActivity;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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

        // Model binding zlyhá ako ModelNotFoundException; navonok je to 404
        // s rovnakým tvarom ako ostatné chyby, nie „no query results for model".
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api', 'api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Záznam sa nenašiel.'], 404);
            }

            return null;
        });
    })->create();
