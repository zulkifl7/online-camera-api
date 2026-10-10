<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CollectionCoverService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function store(Admin $admin, Collection $collection, string $slot, UploadedFile $file): Collection
    {
        if (! in_array($slot, ['mobile', 'desktop'], true)) {
            abort(404);
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, config('online-camera.allowed_mimes'), true) || @getimagesize($file->getPathname()) === false) {
            throw ValidationException::withMessages([
                $slot => 'Choose a JPEG, PNG, or WebP image.',
            ]);
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $column = $slot === 'mobile' ? 'cover_mobile_key' : 'cover_desktop_key';
        $key = 'covers/'.$collection->id.'/'.$slot.'.'.$extension;
        $disk = Storage::disk(config('online-camera.disk'));
        $previous = $collection->{$column};

        $disk->put($key, (string) file_get_contents($file->getPathname()));

        if (is_string($previous) && $previous !== '' && $previous !== $key) {
            $disk->delete($previous);
        }

        $collection->forceFill([$column => $key])->save();

        $this->audit->record(
            request(),
            'admin',
            $admin->id,
            'collection.cover_updated',
            'collection',
            $collection->id,
            [
                'client_id' => $collection->client_id,
                'slot' => $slot,
            ],
        );

        return $collection->refresh();
    }
}
