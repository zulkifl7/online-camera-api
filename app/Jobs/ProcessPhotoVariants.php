<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\ImageProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPhotoVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $photoId) {}

    public function handle(ImageProcessingService $images): void
    {
        $photo = Photo::query()->find($this->photoId);

        if ($photo === null) {
            return;
        }

        $images->process($photo);
    }
}
