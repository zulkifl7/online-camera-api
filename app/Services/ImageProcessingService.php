<?php

namespace App\Services;

use App\Enums\PhotoUploadStatus;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

class ImageProcessingService
{
    public function process(Photo $photo): void
    {
        if ($photo->permanently_deleted_at !== null) {
            return;
        }

        if ($photo->upload_status === PhotoUploadStatus::Ready && $photo->thumbnail_storage_key) {
            return;
        }

        $disk = Storage::disk(config('online-camera.disk'));

        if (! $disk->exists($photo->storage_key)) {
            $photo->forceFill([
                'upload_status' => PhotoUploadStatus::Failed,
                'failure_reason' => 'Original file is missing.',
            ])->save();

            return;
        }

        $photo->forceFill([
            'upload_status' => PhotoUploadStatus::Processing,
            'failure_reason' => null,
        ])->save();

        try {
            $bytes = $disk->get($photo->storage_key);
            $thumbKey = 'thumbnails/'.$photo->client_id.'/'.$photo->id.'.jpg';
            $previewKey = 'previews/'.$photo->client_id.'/'.$photo->id.'.jpg';

            $thumbOk = $this->writeVariant($bytes, $thumbKey, (int) config('online-camera.thumbnail_max'));
            $previewOk = $this->writeVariant($bytes, $previewKey, (int) config('online-camera.preview_max'));

            if (! $thumbOk) {
                $thumbKey = 'thumbnails/'.$photo->client_id.'/'.$photo->id.'.'.$photo->extension();
                $disk->put($thumbKey, $bytes);
            }

            if (! $previewOk) {
                $previewKey = 'previews/'.$photo->client_id.'/'.$photo->id.'.'.$photo->extension();
                $disk->put($previewKey, $bytes);
            }

            $photo->forceFill([
                'thumbnail_storage_key' => $thumbKey,
                'preview_storage_key' => $previewKey,
                'upload_status' => PhotoUploadStatus::Ready,
                'failure_reason' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $photo->forceFill([
                'upload_status' => PhotoUploadStatus::Failed,
                'failure_reason' => 'The photo could not be prepared for the gallery.',
            ])->save();

            report($exception);
        }
    }

    private function writeVariant(string $bytes, string $key, int $max): bool
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg') || ! function_exists('imagecreatetruecolor')) {
            return false;
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $longest = max($width, $height);
        $scale = $longest > 0 ? min(1, $max / $longest) : 1;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            imagedestroy($source);

            return false;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $encoded = imagejpeg($canvas, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if ($encoded !== true || ! is_string($jpeg) || $jpeg === '') {
            return false;
        }

        Storage::disk(config('online-camera.disk'))->put($key, $jpeg);

        return true;
    }
}
