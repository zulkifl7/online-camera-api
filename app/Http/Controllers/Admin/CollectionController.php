<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PhotoUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Http\Resources\AdminCollectionResource;
use App\Http\Resources\AdminPhotoResource;
use App\Models\Client;
use App\Models\Collection;
use App\Services\CollectionCoverService;
use App\Services\CollectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CollectionController extends Controller
{
    public function __construct(
        private readonly CollectionService $collections,
        private readonly CollectionCoverService $covers,
    ) {}

    public function storeEvent(StoreEventRequest $request, Client $client)
    {
        $collection = $this->collections->createEvent($request->user('admin'), $client, $request->validated());

        return (new AdminCollectionResource($collection))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Collection $collection)
    {
        $collection->load('activeCameraLink')->loadCount([
            'photos as visible_photos_count' => function ($query) {
                $query->whereNull('client_hidden_at')
                    ->whereNull('permanently_deleted_at')
                    ->where('upload_status', PhotoUploadStatus::Ready->value);
            },
        ]);

        return new AdminCollectionResource($collection);
    }

    public function update(UpdateCollectionRequest $request, Collection $collection)
    {
        $updated = $this->collections->update($request->user('admin'), $collection, $request->validated());

        return new AdminCollectionResource($updated);
    }

    public function pause(Request $request, Collection $collection)
    {
        return new AdminCollectionResource(
            $this->collections->setUploads($request->user('admin'), $collection, false),
        );
    }

    public function resume(Request $request, Collection $collection)
    {
        return new AdminCollectionResource(
            $this->collections->setUploads($request->user('admin'), $collection, true),
        );
    }

    public function storeCover(Request $request, Collection $collection)
    {
        $request->validate([
            'mobile' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:'.(int) config('online-camera.max_upload_kilobytes')],
            'desktop' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:'.(int) config('online-camera.max_upload_kilobytes')],
        ]);

        if (! $request->hasFile('mobile') && ! $request->hasFile('desktop')) {
            throw ValidationException::withMessages([
                'mobile' => 'Choose a mobile or PC cover image.',
            ]);
        }

        if ($request->hasFile('mobile')) {
            $this->covers->store($request->user('admin'), $collection, 'mobile', $request->file('mobile'));
        }

        if ($request->hasFile('desktop')) {
            $this->covers->store($request->user('admin'), $collection, 'desktop', $request->file('desktop'));
        }

        return new AdminCollectionResource($collection->fresh()->load('activeCameraLink'));
    }

    public function cover(Collection $collection, string $variant)
    {
        return $this->streamCover($collection, $variant);
    }

    public function photos(Request $request, Collection $collection)
    {
        $photos = $collection->photos()
            ->with(['collection:id,name,type', 'client:id,display_name'])
            ->whereNull('permanently_deleted_at')
            ->orderByDesc('created_at')
            ->paginate(24);

        return AdminPhotoResource::collection($photos);
    }

    private function streamCover(Collection $collection, string $variant)
    {
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
