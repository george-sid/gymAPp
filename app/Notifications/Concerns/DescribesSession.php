<?php

namespace App\Notifications\Concerns;

use App\Models\TrainingSession;
use App\Support\LocalTime;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Shared wording for booking emails: the session time in Greek and the gym's details.
 */
trait DescribesSession
{
    protected function when(CarbonInterface $utc): string
    {
        return Str::ucfirst(LocalTime::fromUtc($utc)->locale('el')->translatedFormat('l j F, H:i'));
    }

    protected function gymName(): string
    {
        return (string) app(Settings::class)->get('gym_name');
    }

    protected function withGymDetails(MailMessage $message): MailMessage
    {
        $settings = app(Settings::class);

        foreach (['gym_address' => 'Διεύθυνση', 'gym_phone' => 'Τηλέφωνο'] as $key => $label) {
            if ($value = $settings->get($key)) {
                $message->line("{$label}: {$value}");
            }
        }

        return $message->salutation("Τα λέμε,\n".$this->gymName());
    }

    protected function serviceName(TrainingSession $session): string
    {
        return $session->service->name;
    }
}
