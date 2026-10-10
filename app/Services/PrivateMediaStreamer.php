<?php

namespace App\Services;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaStreamer
{
    public function stream(Photo $photo, string $variant, bool $download = false, ?string $downloadName = null): StreamedResponse
    {
        if ($photo->permanently_deleted_at !== null) {
            abort(404);
        }

        $key = match ($variant) {
            'original' => $photo->storage_key,
            'preview' => $photo->preview_storage_key,
            default => $photo->thumbnail_storage_key,
        };

        if (! is_string($key) || $key === '' || str_starts_with($key, 'deleted/')) {
            abort(404);
        }

        $disk = Storage::disk(config('online-camera.disk'));

        if (! $disk->exists($key)) {
            abort(404);
        }

        $mime = $this->mimeFor($key, $photo->mime_type);
        $name = $downloadName ?: ('photo.'.$this->extensionFor($key, $photo));
        $headers = [
            'Content-Type' => $mime,
            'Cache-Control' => $download ? 'private, no-store' : 'private, max-age=300',
        ];

        if ($download) {
            return $disk->download($key, $name, $headers);
        }

        return $disk->response($key, $name, $headers);
    }

    private function mimeFor(string $key, string $fallback): string
    {
        return match (true) {
            str_ends_with($key, '.png') => 'image/png',
            str_ends_with($key, '.webp') => 'image/webp',
            str_ends_with($key, '.jpg'), str_ends_with($key, '.jpeg') => 'image/jpeg',
            default => $fallback,
        };
    }

    private function extensionFor(string $key, Photo $photo): string
    {
        $ext = pathinfo($key, PATHINFO_EXTENSION);

        return $ext !== '' ? $ext : $photo->extension();
    }
}
