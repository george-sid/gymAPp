<?php

namespace App\Listeners;

use App\Events\TrainingSessionRescheduled;
use App\Models\Booking;
use App\Notifications\SessionRescheduledNotification;
use App\Support\Settings;

/**
 * Emails every booked client when their upcoming session moves.
 */
class NotifyClientsOfReschedule
{
    public function __construct(private Settings $settings) {}

    public function handle(TrainingSessionRescheduled $event): void
    {
        $session = $event->session;

        if (! $this->settings->get('notify_clients') || $session->starts_at->isPast()) {
            return;
        }

        $session->activeBookings()
            ->with('client')
            ->get()
            ->map(fn (Booking $booking) => $booking->client)
            ->filter(fn ($client) => $client->email && $client->is_active)
            ->each(fn ($client) => $client->notify(new SessionRescheduledNotification($session->load('service'), $event->previousStartsAt)));
    }
}
