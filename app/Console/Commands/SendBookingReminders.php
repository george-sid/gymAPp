<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\SessionReminderNotification;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('bookings:send-reminders')]
#[Description('Email clients a reminder before their upcoming sessions')]
class SendBookingReminders extends Command
{
    public function handle(Settings $settings): int
    {
        $windowEnd = now()->addHours($settings->integer('reminder_hours_before'));
        $sent = 0;

        Booking::query()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->whereNull('reminder_sent_at')
            ->whereHas('trainingSession', fn (Builder $query) => $query
                ->notCancelled()
                ->where('starts_at', '>', now())
                ->where('starts_at', '<=', $windowEnd))
            ->whereHas('client', fn (Builder $query) => $query->active()->whereNotNull('email'))
            ->with(['client', 'trainingSession.service'])
            ->chunkById(100, function ($bookings) use (&$sent): void {
                foreach ($bookings as $booking) {
                    $booking->client->notify(new SessionReminderNotification($booking));
                    $booking->update(['reminder_sent_at' => now()]);
                    $sent++;
                }
            });

        $this->components->info("Στάλθηκαν {$sent} υπενθυμίσεις.");

        return self::SUCCESS;
    }
}
