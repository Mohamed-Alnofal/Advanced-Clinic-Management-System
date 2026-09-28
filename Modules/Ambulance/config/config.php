<?php

return [
    'name' => 'Ambulance',

    /*
    |--------------------------------------------------------------------------
    | SOS dispatch lifecycle (minutes / hours)
    |--------------------------------------------------------------------------
    | Tunable via .env — no code change needed for ops tweaks.
    */
    'dispatch' => [
        /** Flag pending SOS for company attention after this many minutes. */
        'escalate_pending_after_minutes' => (int) env('AMBULANCE_ESCALATE_MINUTES', 2),

        /** Auto-cancel unanswered pending SOS after this many minutes. */
        'expire_pending_after_minutes' => (int) env('AMBULANCE_EXPIRE_PENDING_MINUTES', 5),

        /** Auto-cancel stuck assigned/arrived missions after this many hours. */
        'expire_assigned_after_hours' => (int) env('AMBULANCE_EXPIRE_ASSIGNED_HOURS', 6),

        /** How long a driver offer stays open before rotating to the next unit. */
        'offer_ttl_minutes' => (int) env('AMBULANCE_OFFER_TTL_MINUTES', 2),
    ],
];
