<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', env('APP_NAME', 'Event API') . ' geocoder'),
        'cache_ttl' => env('NOMINATIM_CACHE_TTL', 86400),
    ],

    'municipality_resolver' => [
        'cache_ttl' => env('MUNICIPALITY_RESOLVER_CACHE_TTL', 86400),
    ],

    'wikipedia' => [
        'user_agent' => env('WIKIPEDIA_USER_AGENT', env('APP_NAME', 'Event API') . ' wikipedia-enricher'),
        'cache_ttl' => env('WIKIPEDIA_CACHE_TTL', 86400),
    ],

    'venue_detection' => [
        'attach' => [
            'max_bytes' => env('VENUE_DETECTION_ATTACH_MAX_BYTES', 10485760),
            'allowed_mime_types' => env('VENUE_DETECTION_ATTACH_ALLOWED_MIME_TYPES', 'image/jpeg,image/png,image/webp,image/gif'),
        ],
    ],

    'pdf_converter' => [
        'url'   => env('PDF_CONVERTER_URL', 'http://78.47.38.184'),
        'token' => env('PDF_CONVERTER_TOKEN', ''),
    ],

    'imports' => [
        'user_agent' => env('IMPORTS_USER_AGENT', env('APP_NAME', 'Event API') . ' importer'),
        'detect_canal_with_ai' => (bool) env('IMPORTS_DETECT_CANAL_WITH_AI', false),
        // Popisy nových kanálov/miest z importu píše AI; pri vypnutí sa použije
        // neutrálny vetný fallback (kanál) alebo prázdny popis (miesto).
        'describe_with_ai' => (bool) env('IMPORTS_DESCRIBE_WITH_AI', env('IMPORTS_DETECT_CANAL_WITH_AI', false)),
        'sources' => [
            'urls' => array_values(array_filter(array_map(
                static fn (string $url) => trim($url),
                explode(',', (string) env('IMPORT_SOURCE_URLS', 'https://www.ecav.sk/aktuality/pozvanky,https://www.tkkbs.sk/search.php?rstext=pozvanka&rskde=tsl,https://www.vyveska.sk/zoznam-podujati/najnovsie/'))
            ))),
            'max_pages' => max(1, (int) env('IMPORT_SOURCE_MAX_PAGES', 1)),
        ],
    ],

];
