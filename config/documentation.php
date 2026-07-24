<?php

return [
    'api_token_hash' => env('DOCUMENTATION_API_TOKEN_HASH'),

    'rate_limit' => (int) env(
        'DOCUMENTATION_API_RATE_LIMIT',
        120
    ),
];
