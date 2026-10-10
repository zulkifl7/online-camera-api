<?php

namespace App\Jobs;

use App\Models\DownloadJob;
use App\Services\BulkDownloadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildDownloadArchive implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $downloadJobId) {}

    public function handle(BulkDownloadService $downloads): void
    {
        $job = DownloadJob::query()->find($this->downloadJobId);

        if ($job === null) {
            return;
        }

        $downloads->build($job);
    }
}
