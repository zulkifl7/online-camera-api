<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Client;
use App\Models\Photo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PhotoVisibilityService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  list<string>  $photoIds
     */
    public function hide(Client $client, array $photoIds): int
    {
        $ids = array_values(array_unique($photoIds));

        return DB::transaction(function () use ($client, $ids) {
            $photos = Photo::query()
                ->where('client_id', $client->id)
                ->whereIn('id', $ids)
                ->whereNull('permanently_deleted_at')
                ->lockForUpdate()
                ->get();

            if ($photos->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'photo_ids' => 'One or more photos could not be updated.',
                ]);
            }

            $now = now();

            foreach ($photos as $photo) {
                if ($photo->client_hidden_at !== null) {
                    continue;
                }

                $photo->forceFill([
                    'client_hidden_at' => $now,
                    'client_hidden_by' => $client->id,
                ])->save();

                $this->audit->record(
                    request(),
                    'client',
                    $client->id,
                    'photo.hidden',
                    'photo',
                    $photo->id,
                    ['collection_id' => $photo->collection_id],
                );
            }

            return $photos->count();
        });
    }

    /**
     * @param  list<string>  $photoIds
     */
    public function restoreMany(Admin $admin, string $clientId, array $photoIds): int
    {
        $ids = array_values(array_unique($photoIds));

        return DB::transaction(function () use ($admin, $clientId, $ids) {
            $photos = $this->hiddenPhotosForClient($clientId, $ids);

            foreach ($photos as $photo) {
                $this->restore($admin, $photo);
            }

            return $photos->count();
        });
    }

    /**
     * @param  list<string>  $photoIds
     */
    public function permanentlyDeleteMany(Admin $admin, string $clientId, array $photoIds): int
    {
        $ids = array_values(array_unique($photoIds));

        return DB::transaction(function () use ($admin, $clientId, $ids) {
            $photos = $this->hiddenPhotosForClient($clientId, $ids);

            foreach ($photos as $photo) {
                $this->permanentlyDelete($admin, $photo);
            }

            return $photos->count();
        });
    }

    /**
     * @param  list<string>  $ids
     */
    private function hiddenPhotosForClient(string $clientId, array $ids)
    {
        $photos = Photo::query()
            ->where('client_id', $clientId)
            ->whereIn('id', $ids)
            ->whereNotNull('client_hidden_at')
            ->whereNull('permanently_deleted_at')
            ->lockForUpdate()
            ->get();

        if ($photos->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'photo_ids' => 'One or more photos could not be updated.',
            ]);
        }

        return $photos;
    }

    public function restore(Admin $admin, Photo $photo): Photo
    {
        if ($photo->permanently_deleted_at !== null || $photo->client_hidden_at === null) {
            abort(404);
        }

        $photo->forceFill([
            'client_hidden_at' => null,
            'client_hidden_by' => null,
        ])->save();

        $this->audit->record(
            request(),
            'admin',
            $admin->id,
            'photo.restored',
            'photo',
            $photo->id,
            [
                'client_id' => $photo->client_id,
                'collection_id' => $photo->collection_id,
            ],
        );

        return $photo->refresh();
    }

    public function permanentlyDelete(Admin $admin, Photo $photo): void
    {
        if ($photo->permanently_deleted_at !== null) {
            abort(404);
        }

        $disk = Storage::disk(config('online-camera.disk'));

        foreach ([$photo->storage_key, $photo->thumbnail_storage_key, $photo->preview_storage_key] as $key) {
            if (is_string($key) && $key !== '') {
                $disk->delete($key);
            }
        }

        $photo->forceFill([
            'storage_key' => 'deleted/'.$photo->id,
            'thumbnail_storage_key' => null,
            'preview_storage_key' => null,
            'permanently_deleted_at' => now(),
            'permanently_deleted_by' => $admin->id,
        ])->save();

        $this->audit->record(
            request(),
            'admin',
            $admin->id,
            'photo.permanently_deleted',
            'photo',
            $photo->id,
            [
                'client_id' => $photo->client_id,
                'collection_id' => $photo->collection_id,
            ],
        );
    }
}
