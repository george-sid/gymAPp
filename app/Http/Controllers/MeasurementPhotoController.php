<?php

namespace App\Http\Controllers;

use App\Models\MeasurementPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a private progress photo to staff allowed to see the client.
 */
class MeasurementPhotoController extends Controller
{
    public function __invoke(MeasurementPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $photo->load('measurement.client')->measurement->client);

        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
