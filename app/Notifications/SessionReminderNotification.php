<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\DescribesSession;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email reminder sent before an upcoming session.
 */
class SessionReminderNotification extends Notification implements ShouldQueue
{
    use DescribesSession, Queueable;

    public function __construct(public Booking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $session = $this->booking->trainingSession;
        $freeCancellationHours = app(Settings::class)->integer('free_cancellation_hours');

        $message = (new MailMessage)
            ->subject('Υπενθύμιση ραντεβού · '.$this->when($session->starts_at))
            ->greeting("Γεια σου {$notifiable->name},")
            ->line("Σου θυμίζουμε το ραντεβού σου: **{$this->serviceName($session)}**, {$this->when($session->starts_at)}.");

        if ($freeCancellationHours > 0) {
            $message->line("Αν δεν μπορείς να έρθεις, ενημέρωσέ μας τουλάχιστον {$freeCancellationHours} ώρες πριν.");
        }

        return $this->withGymDetails($message);
    }
}
