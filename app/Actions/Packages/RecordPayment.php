<?php

namespace App\Actions\Packages;

use App\Models\Payment;
use App\Models\User;

class RecordPayment
{
    /**
     * @param  array{amount: string|float, method: string, paid_on: string, client_package_id: ?int, receipt_number: ?string, notes: ?string}  $data
     */
    public function handle(User $client, array $data, User $recordedBy): Payment
    {
        return Payment::query()->create([
            ...$data,
            'client_id' => $client->id,
            'recorded_by' => $recordedBy->id,
        ]);
    }
}
