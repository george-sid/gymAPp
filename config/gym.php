<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default settings
    |--------------------------------------------------------------------------
    |
    | Used until the trainer changes them from the Settings page (stored in the
    | `settings` table). Read them through App\Support\Settings.
    |
    */

    'defaults' => [
        // Booking rules
        'buffer_minutes' => 10,
        'slot_interval_minutes' => 30,
        'min_notice_hours' => 2,
        'max_advance_days' => 30,
        'free_cancellation_hours' => 12,
        'reminder_hours_before' => 24,
        'notify_clients' => true,

        // Gym details (used in emails)
        'gym_name' => env('APP_NAME', 'GymApp'),
        'gym_address' => null,
        'gym_phone' => null,
        'gym_email' => null,
    ],

];
