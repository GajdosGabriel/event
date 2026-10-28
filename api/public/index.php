<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Crawler poslaný koreňovým .htaccess na bot-render (blok 2). Apache prepisuje
// interne, REQUEST_URI ostáva pôvodná SPA cesta — tu sa z nej stane
// /api/prerender?path=…, aby routa sedela a adresa pre Google ostala tá istá.
// Značku nastavuje len mod_rewrite (po internom prepise s prefixom REDIRECT_),
// klient ju hlavičkou podvrhnúť nevie.
if (($_SERVER['REDIRECT_EVENT_PRERENDER'] ?? $_SERVER['EVENT_PRERENDER'] ?? null) === '1') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $query = 'path='.rawurlencode($path);
    if (($_SERVER['QUERY_STRING'] ?? '') !== '') {
        $query .= '&'.$_SERVER['QUERY_STRING'];
    }
    $_SERVER['REQUEST_URI'] = '/api/prerender?'.$query;
    $_SERVER['QUERY_STRING'] = $query;
    parse_str($query, $_GET);
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
