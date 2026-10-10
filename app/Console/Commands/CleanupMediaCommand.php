<?php

namespace App\Console\Commands;

use App\Enums\DownloadJobStatus;
use App\Models\DownloadJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupMediaCommand extends Command
{
    protected $signature = 'media:cleanup';

    protected $description = 'Delete expired gallery ZIP archives';

    public function handle(): int
    {
        $disk = Storage::disk(config('online-camera.disk'));
        $jobs = DownloadJob::query()
            ->whereNotNull('archive_storage_key')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($jobs as $job) {
            if (is_string($job->archive_storage_key)) {
                $disk->delete($job->archive_storage_key);
            }

            $job->forceFill([
                'archive_storage_key' => null,
                'status' => DownloadJobStatus::Failed,
                'error_message' => 'This download expired.',
            ])->save();
        }

        $this->info('Expired download archives cleaned: '.$jobs->count());

        return self::SUCCESS;
    }
}
