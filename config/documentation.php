<?php

return [
    'legacy_token_enabled' => (bool) env(
        'DOCUMENTATION_API_LEGACY_TOKEN_ENABLED',
        false
    ),

    'api_token_hash' => env('DOCUMENTATION_API_TOKEN_HASH'),

    'rate_limit' => (int) env(
        'DOCUMENTATION_API_RATE_LIMIT',
        120
    ),
];
