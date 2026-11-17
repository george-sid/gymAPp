<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\DescribesSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification implements ShouldQueue
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

        return $this->withGymDetails((new MailMessage)
            ->subject('Ακύρωση ραντεβού · '.$this->when($session->starts_at))
            ->greeting("Γεια σου {$notifiable->name},")
            ->line("Το ραντεβού σου ({$this->serviceName($session)}, {$this->when($session->starts_at)}) ακυρώθηκε.")
            ->line('Επικοινώνησε μαζί μας για να κλείσουμε νέα ώρα.'));
    }
}
