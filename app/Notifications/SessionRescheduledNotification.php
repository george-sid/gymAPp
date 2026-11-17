<?php

namespace App\Notifications;

use App\Models\TrainingSession;
use App\Notifications\Concerns\DescribesSession;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionRescheduledNotification extends Notification implements ShouldQueue
{
    use DescribesSession, Queueable;

    public function __construct(
        public TrainingSession $session,
        public CarbonImmutable $previousStartsAt,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->withGymDetails((new MailMessage)
            ->subject('Αλλαγή ώρας ραντεβού · '.$this->when($this->session->starts_at))
            ->greeting("Γεια σου {$notifiable->name},")
            ->line("Το ραντεβού σου ({$this->serviceName($this->session)}) άλλαξε ώρα.")
            ->line("Πριν: ~~{$this->when($this->previousStartsAt)}~~")
            ->line("**Νέα ώρα: {$this->when($this->session->starts_at)}**")
            ->line('Αν η νέα ώρα δεν σε εξυπηρετεί, επικοινώνησε μαζί μας.'));
    }
}
