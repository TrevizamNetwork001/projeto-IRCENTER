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

        'payment_webhooks_enabled' => env(
            'PAYMENT_WEBHOOKS_ENABLED',
            false
        ),

        'allow_general_email_fallback' => env(
            'FINANCE_GENERAL_EMAIL_FALLBACK',
            false
        ),
    ],

    'fiscal' => [
        'enabled' => env('FISCAL_ENABLED', false),

        'provider' => env('FISCAL_PROVIDER', env('NFSE_PROVIDER', 'fake')),

        'environment' => env('FISCAL_ENVIRONMENT', 'homologation'),

        'artifact_disk' => env('FISCAL_ARTIFACT_DISK', 'local'),

        'artifact_max_kb' => env('FISCAL_ARTIFACT_MAX_KB', 5120),

        'portal_url' => 'https://www.nfse.gov.br/EmissorNacional/',

        'artifact_max_kb' => env('FISCAL_ARTIFACT_MAX_KB', 5120),

        'portal_url' => 'https://www.nfse.gov.br/EmissorNacional/',

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
