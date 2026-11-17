<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Notifications\BookingCancelledNotification;
use App\Support\Settings;

/**
 * Emails the client when the trainer cancels their upcoming booking.
 */
class NotifyClientOfCancellation
{
    public function __construct(private Settings $settings) {}

    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking->load(['client', 'trainingSession.service']);
        $client = $booking->client;

        $shouldNotify = $this->settings->get('notify_clients')
            && $booking->cancelled_by !== $client->id
            && $booking->trainingSession->starts_at->isFuture()
            && $client->email
            && $client->is_active;

        if ($shouldNotify) {
            $client->notify(new BookingCancelledNotification($booking));
        }
    }
}
