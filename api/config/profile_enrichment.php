<?php

return [
    // Zapnutie a mesačný limit sú predvolené hodnoty — mení ich admin na
    // stránke Spotreba AI (AppSettings, tabuľka app_settings), nie .env.
    'enabled' => true,
    'delay_minutes' => 120,
    'batch' => 2,
    'max_seconds' => 45,
    'max_attempts' => 3,
    'retry_hours' => 6,
    // Applies only to this feature, including web-search fees. Zero means unlimited.
    'monthly_limit_usd' => 1.0,
    'model' => env('PROFILE_ENRICHMENT_MODEL', 'gpt-4.1-mini'),
    'request_timeout' => 30,
    'search_cost_usd' => (float) env('PROFILE_ENRICHMENT_SEARCH_COST_USD', 0.01),
];
