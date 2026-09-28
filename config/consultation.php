<?php

return [
    /*
    | Video slots are booked in wall-clock clinic time, not UTC.
    | Hostinger APP_TIMEZONE is often UTC, which made "join 15 minutes
    | before 10:00" impossible at 10:00 in Damascus.
    */
    'timezone' => env('CONSULTATION_TIMEZONE', env('APP_TIMEZONE', 'Asia/Damascus')),
];
