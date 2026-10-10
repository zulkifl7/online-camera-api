<?php

namespace App\Http\Controllers\Client;

use App\Enums\CollectionStatus;
use App\Enums\PhotoUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientCollectionResource;
use App\Http\Resources\ClientPhotoResource;
use App\Models\Collection;
use App\Models\Photo;
use App\Services\GalleryQueryService;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function __construct(private readonly GalleryQueryService $galleries) {}

    public function collections(Request $request)
    {
        $client = $request->user('client');

        $collections = Collection::query()
            ->where('client_id', $client->id)
            ->where('status', CollectionStatus::Active)
            ->withCount([
                'photos as visible_photos_count' => function ($query) {
                    $query->whereNull('client_hidden_at')
                        ->whereNull('permanently_deleted_at')
                        ->where('upload_status', PhotoUploadStatus::Ready->value);
                },
            ])
            ->orderByRaw("case when type = 'common' then 0 else 1 end")
            ->orderBy('event_date')
            ->orderBy('name')
            ->get();

        $covers = Photo::query()
            ->where('client_id', $client->id)
            ->whereNull('client_hidden_at')
            ->whereNull('permanently_deleted_at')
            ->where('upload_status', PhotoUploadStatus::Ready)
            ->orderByDesc('created_at')
            ->get(['id', 'collection_id'])
            ->unique('collection_id')
            ->pluck('id', 'collection_id');

        $collections->each(function (Collection $collection) use ($covers) {
            $collection->cover_photo_id = $covers[$collection->id] ?? null;
        });

        return ClientCollectionResource::collection($collections);
    }

    public function collectionPhotos(Request $request, string $collection)
    {
        $paginator = $this->galleries->visiblePhotos($request->user('client'), $collection);

        return ClientPhotoResource::collection($paginator);
    }

    public function photos(Request $request)
    {
        $collectionId = $request->query('collection_id');
        $paginator = $this->galleries->visiblePhotos(
            $request->user('client'),
            is_string($collectionId) && $collectionId !== '' ? $collectionId : null,
        );

        return ClientPhotoResource::collection($paginator);
    }
}
