<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\CollectionStatus;
use App\Enums\PhotoUploadStatus;
use App\Jobs\ProcessPhotoVariants;
use App\Models\CameraLink;
use App\Models\Photo;
use App\Models\UploadSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhotoUploadService
{
    public function __construct(
        private readonly CameraLinkService $cameraLinks,
        private readonly AuditLogService $audit,
    ) {}

    public function store(string $rawToken, UploadedFile $file, ?string $idempotencyKey): Photo
    {
        $link = $this->cameraLinks->resolveActive($rawToken);
        $link->loadMissing('collection.client');
        $collection = $link->collection;
        $client = $collection->client;

        if ($client->status !== ClientStatus::Active || $collection->status !== CollectionStatus::Active) {
            abort(403, 'Uploads are paused for this camera link.');
        }

        if (! $collection->uploads_enabled) {
            abort(403, 'Uploads are paused for this camera link.');
        }

        if ($idempotencyKey) {
            $existing = $this->existingUpload($link, $idempotencyKey);

            if ($existing instanceof Photo) {
                return $existing;
            }
        }

        $image = $this->inspect($file);
        $photoId = (string) Str::orderedUuid();
        $extension = $this->extension($image['mime']);
        $key = 'originals/'.$client->id.'/'.$photoId.'.'.$extension;
        $disk = Storage::disk(config('online-camera.disk'));
        $disk->put($key, $image['bytes']);

        try {
            $photo = DB::transaction(function () use ($link, $client, $collection, $image, $file, $key, $photoId, $idempotencyKey) {
                $photo = new Photo([
                    'client_id' => $client->id,
                    'collection_id' => $collection->id,
                    'storage_key' => $key,
                    'original_filename' => $this->safeFilename($file->getClientOriginalName()),
                    'mime_type' => $image['mime'],
                    'file_size_bytes' => strlen($image['bytes']),
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'checksum' => hash('sha256', $image['bytes']),
                    'upload_status' => PhotoUploadStatus::Pending,
                ]);
                $photo->id = $photoId;
                $photo->save();

                if ($idempotencyKey) {
                    UploadSession::query()->create([
                        'camera_link_id' => $link->id,
                        'idempotency_key' => $idempotencyKey,
                        'photo_id' => $photo->id,
                    ]);
                }

                $this->audit->record(
                    request(),
                    'guest',
                    null,
                    'photo.uploaded',
                    'photo',
                    $photo->id,
                    [
                        'client_id' => $client->id,
                        'collection_id' => $collection->id,
                        'camera_link_id' => $link->id,
                    ],
                );

                return $photo;
            });
        } catch (UniqueConstraintViolationException) {
            $disk->delete($key);
            $existing = $idempotencyKey ? $this->existingUpload($link, $idempotencyKey) : null;

            if ($existing instanceof Photo) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'photo' => 'This upload was already received. Take another photo to send a new one.',
            ]);
        } catch (\Throwable $exception) {
            $disk->delete($key);
            throw $exception;
        }

        ProcessPhotoVariants::dispatch($photo->id);

        return $photo->fresh() ?? $photo;
    }

    private function existingUpload(CameraLink $link, string $idempotencyKey): ?Photo
    {
        $session = UploadSession::query()
            ->where('camera_link_id', $link->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($session?->photo_id === null) {
            return null;
        }

        return Photo::query()->find($session->photo_id);
    }

    /**
     * @return array{bytes: string, mime: string, width: int, height: int}
     */
    private function inspect(UploadedFile $file): array
    {
        $maxBytes = (int) config('online-camera.max_upload_kilobytes') * 1024;

        if ($file->getSize() !== false && $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'photo' => 'That photo is larger than the upload limit.',
            ]);
        }

        $bytes = file_get_contents($file->getRealPath() ?: '');

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages([
                'photo' => 'The photo could not be read.',
            ]);
        }

        if (strlen($bytes) > $maxBytes) {
            throw ValidationException::withMessages([
                'photo' => 'That photo is larger than the upload limit.',
            ]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        $allowed = config('online-camera.allowed_mimes');

        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'photo' => 'Upload a JPEG, PNG, or WebP photo.',
            ]);
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw ValidationException::withMessages([
                'photo' => 'The file is not a valid image.',
            ]);
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $maxEdge = (int) config('online-camera.max_edge_pixels');

        if ($width < 1 || $height < 1 || $width > $maxEdge || $height > $maxEdge) {
            throw ValidationException::withMessages([
                'photo' => 'The photo dimensions are not supported.',
            ]);
        }

        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function safeFilename(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: null;

        return $base ? Str::limit($base, 120, '') : null;
    }
}
