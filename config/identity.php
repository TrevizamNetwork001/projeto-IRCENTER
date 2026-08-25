<?php

return [
    'mfa_enforcement' => env('MFA_ENFORCEMENT', false),
    'password_confirmation_timeout' => (int) env('PASSWORD_CONFIRMATION_TIMEOUT', 900),
    'totp_window' => 1,
];
