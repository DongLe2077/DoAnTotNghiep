<?php
return [
    'pusher' => [
        'id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER'),
    ],
    // Enable only after the reviewed ownership backfill and acceptance checks.
    'legacy_payos_property_id' => (int) env('PAYOS_LEGACY_PROPERTY_ID', 1),
    'enabled' => (bool) env('PROPERTY_TENANCY_ENABLED', false),
];
