<?php

return [

    'database_connection' => 'finance_fiscal',

    'finance' => [
        'enabled' => env('FINANCE_ENABLED', false),

        'automation_enabled' => env(
            'FINANCE_AUTOMATION_ENABLED',
            false
        ),

        'payment_provider' => env(
            'PAYMENT_PROVIDER',
            'fake'
        ),

        'payment_live_enabled' => env(
            'PAYMENT_LIVE_ENABLED',
            false
        ),
    ],

    'fiscal' => [
        'enabled' => env('FISCAL_ENABLED', false),

        'nfse_provider' => env(
            'NFSE_PROVIDER',
            'fake'
        ),

        'transmission_enabled' => env(
            'NFSE_TRANSMISSION_ENABLED',
            false
        ),

        'live_enabled' => env(
            'NFSE_LIVE_ENABLED',
            false
        ),
    ],

];
