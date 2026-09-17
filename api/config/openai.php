<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key and Organization
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API Key and organization. This will be
    | used to authenticate with the OpenAI API - you can find your API key
    | and organization on your OpenAI dashboard, at https://openai.com.
    */

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Project
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API project. This is used optionally in
    | situations where you are using a legacy user API key and need association
    | with a project. This is not required for the newer API keys.
    */
    'project' => env('OPENAI_PROJECT'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Base URL
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API base URL used to make requests. This
    | is needed if using a custom API endpoint. Defaults to: api.openai.com/v1
    */
    'base_uri' => env('OPENAI_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 30 seconds.
    */

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Billing Alert Cooldown
    |--------------------------------------------------------------------------
    |
    | Keď OpenAI odmietne volanie pre kredit (insufficient_quota), dostanú
    | super-admini e-mail — najviac raz za toľko hodín (App\Services\OpenAI\OpenAiBillingAlert).
    */
    /*
    | Cenník v USD za milión tokenov (vstup / výstup). Slúži len na odhad
    | spotreby v administrácii — skutočné čísla sú na platform.openai.com/usage
    | a cenník sa môže zmeniť (openai.com/api/pricing).
    */
    'prices' => [
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
        'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40],
        'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
    ],

    'billing_alert_cooldown_hours' => env('OPENAI_BILLING_ALERT_COOLDOWN_HOURS', 6),
];
