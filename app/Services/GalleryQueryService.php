<?php

namespace App\Services;

use App\Enums\PhotoUploadStatus;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Photo;
use Illuminate\Contracts\Pagination\CursorPaginator;

class GalleryQueryService
{
    public function visiblePhotos(Client $client, ?string $collectionId = null): CursorPaginator
    {
        $query = Photo::query()
            ->where('client_id', $client->id)
            ->whereNull('client_hidden_at')
            ->whereNull('permanently_deleted_at')
            ->where('upload_status', PhotoUploadStatus::Ready)
            ->with('collection:id,name,type,client_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($collectionId !== null) {
            $collection = Collection::query()
                ->where('client_id', $client->id)
                ->where('id', $collectionId)
                ->where('status', 'active')
                ->firstOrFail();

            $query->where('collection_id', $collection->id);
        }

        return $query->cursorPaginate(24);
    }

    public function visiblePhoto(Client $client, string $photoId): Photo
    {
        return Photo::query()
            ->where('client_id', $client->id)
            ->where('id', $photoId)
            ->whereNull('client_hidden_at')
            ->whereNull('permanently_deleted_at')
            ->where('upload_status', PhotoUploadStatus::Ready)
            ->firstOrFail();
    }
}
