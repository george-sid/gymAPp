<?php

namespace App\Events;

use App\Models\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A session moved to another time (drag & drop or edit).
 */
class TrainingSessionRescheduled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public TrainingSession $session,
        public CarbonImmutable $previousStartsAt,
    ) {}
}
