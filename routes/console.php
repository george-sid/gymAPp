<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:send-reminders')
    ->everyTenMinutes()
    ->withoutOverlapping(10);
