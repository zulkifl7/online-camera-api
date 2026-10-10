<?php

namespace App\Http\Controllers\Guest;

use App\Enums\ClientStatus;
use App\Enums\CollectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPhotoRequest;
use App\Services\CameraLinkService;
use App\Services\PhotoUploadService;
use App\Support\PublicUrl;
use Illuminate\Support\Facades\Storage;

class CameraController extends Controller
{
    public function __construct(
        private readonly CameraLinkService $links,
        private readonly PhotoUploadService $uploads,
    ) {}

    public function show(string $token)
    {
        $link = $this->links->resolveActive($token);
        $link->loadMissing('collection.client');
        $collection = $link->collection;
        $client = $collection->client;
        $uploadsEnabled = $client->status === ClientStatus::Active
            && $collection->status === CollectionStatus::Active
            && $collection->uploads_enabled;

        return response()->json([
            'data' => [
                'heading' => $collection->cameraHeading(),
                'kind' => $collection->type->value,
                'client_name' => $client->display_name,
                'upload_note' => "Photos from this link are uploaded to {$client->display_name}'s Event Gallery.",
                'instruction' => 'Capture a moment to share with the couple.',
                'uploads_enabled' => $uploadsEnabled,
                'max_upload_bytes' => (int) config('online-camera.max_upload_kilobytes') * 1024,
                'allowed_types' => config('online-camera.allowed_mimes'),
                'cover_mobile_url' => $collection->cover_mobile_key
                    ? PublicUrl::to('/api/v1/public/camera/'.$token.'/covers/mobile')
                    : null,
                'cover_desktop_url' => $collection->cover_desktop_key
                    ? PublicUrl::to('/api/v1/public/camera/'.$token.'/covers/desktop')
                    : null,
            ],
        ]);
    }

    public function store(UploadPhotoRequest $request, string $token)
    {
        $idempotency = $request->header('Idempotency-Key');

        if (is_string($idempotency) && ! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $idempotency)) {
            return response()->json([
                'message' => 'The idempotency key is invalid.',
                'errors' => ['idempotency_key' => ['Use 8 to 100 letters, numbers, dashes, or underscores.']],
            ], 422);
        }

        $photo = $this->uploads->store($token, $request->file('photo'), is_string($idempotency) ? $idempotency : null);

        return response()->json([
            'data' => [
                'id' => $photo->id,
                'status' => $photo->upload_status->value,
            ],
        ], 201);
    }

    public function cover(string $token, string $variant)
    {
        $link = $this->links->resolveActive($token);
        $collection = $link->collection;
        $key = match ($variant) {
            'mobile' => $collection->cover_mobile_key,
            'desktop' => $collection->cover_desktop_key,
            default => null,
        };

        if (! is_string($key) || $key === '') {
            abort(404);
        }

        $disk = Storage::disk(config('online-camera.disk'));
        if (! $disk->exists($key)) {
            abort(404);
        }

        $mime = match (true) {
            str_ends_with($key, '.png') => 'image/png',
            str_ends_with($key, '.webp') => 'image/webp',
            default => 'image/jpeg',
        };

        return $disk->response($key, 'cover.'.$variant, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
