<?php

return [
    'enabled' => (bool) env('SCHEDULING_ENABLED', false),
    'default_timezone' => env('SCHEDULING_TIMEZONE', env('BUSINESS_TIMEZONE', 'America/Sao_Paulo')),
    'slot_interval' => (int) env('SCHEDULING_SLOT_INTERVAL', 15),
    'max_booking_horizon' => (int) env('SCHEDULING_MAX_DAYS_AHEAD', 90),
    'minimum_notice' => (int) env('SCHEDULING_MINIMUM_NOTICE', 60),
    'reminder_times' => [1440, 60],
    'public_rate_limit' => (int) env('SCHEDULING_PUBLIC_RATE_LIMIT', 60),
    'booking_rate_limit' => (int) env('SCHEDULING_BOOKING_RATE_LIMIT', 10),
];
