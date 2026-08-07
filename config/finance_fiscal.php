<?php

return [

    'database_connection' => 'finance_fiscal',

    'timezone' => env(
        'FINANCE_FISCAL_TIMEZONE',
        'America/Sao_Paulo'
    ),

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

        'allow_general_email_fallback' => env(
            'FINANCE_GENERAL_EMAIL_FALLBACK',
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


    'providers' => [
        'efi' => [
            'environment' => env(
                'EFI_ENVIRONMENT',
                'homologation'
            ),

            'client_id' => env(
                'EFI_CLIENT_ID'
            ),

            'client_secret' => env(
                'EFI_CLIENT_SECRET'
            ),

            'notification_url' => env(
                'EFI_NOTIFICATION_URL'
            ),
        ],
    ],

];
