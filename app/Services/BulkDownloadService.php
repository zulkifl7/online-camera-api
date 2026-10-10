<?php

namespace App\Services;

use App\Enums\DownloadJobStatus;
use App\Enums\DownloadMode;
use App\Enums\PhotoUploadStatus;
use App\Jobs\BuildDownloadArchive;
use App\Models\Client;
use App\Models\DownloadJob;
use App\Models\Photo;
use App\Support\StoredZip;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BulkDownloadService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  list<string>  $photoIds
     */
    public function queue(Client $client, array $photoIds): DownloadJob
    {
        $ids = array_values(array_unique($photoIds));
        $maxItems = (int) config('online-camera.max_bulk_photos');

        if (count($ids) > $maxItems) {
            throw ValidationException::withMessages([
                'photo_ids' => 'You can download up to '.$maxItems.' photos at a time.',
            ]);
        }

        $photos = Photo::query()
            ->where('client_id', $client->id)
            ->whereIn('id', $ids)
            ->whereNull('client_hidden_at')
            ->whereNull('permanently_deleted_at')
            ->where('upload_status', PhotoUploadStatus::Ready)
            ->with('collection:id,name')
            ->get();

        if ($photos->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'photo_ids' => 'One or more photos cannot be downloaded.',
            ]);
        }

        $total = (int) $photos->sum('file_size_bytes');

        if ($total > (int) config('online-camera.max_bulk_bytes')) {
            throw ValidationException::withMessages([
                'photo_ids' => 'The selected photos are too large to download at once.',
            ]);
        }

        $mode = $photos->count() === 1 ? DownloadMode::Single : DownloadMode::Archive;
        $fileName = $mode === DownloadMode::Single
            ? $this->singleName($photos->first())
            : 'gallery-'.now()->format('Ymd-His').'.zip';

        $job = DownloadJob::query()->create([
            'client_id' => $client->id,
            'mode' => $mode,
            'status' => DownloadJobStatus::Queued,
            'photo_ids' => $photos->pluck('id')->all(),
            'file_name' => $fileName,
            'expires_at' => now()->addMinutes((int) config('online-camera.download_ttl_minutes')),
        ]);

        $this->audit->record(
            request(),
            'client',
            $client->id,
            'download.created',
            'download_job',
            $job->id,
            [
                'mode' => $mode->value,
                'photo_count' => $photos->count(),
            ],
        );

        BuildDownloadArchive::dispatch($job->id);

        return $job->fresh() ?? $job;
    }

    public function build(DownloadJob $job): void
    {
        if ($job->status === DownloadJobStatus::Ready) {
            return;
        }

        $job->forceFill(['status' => DownloadJobStatus::Processing, 'error_message' => null])->save();

        try {
            $photos = Photo::query()
                ->where('client_id', $job->client_id)
                ->whereIn('id', $job->photo_ids ?? [])
                ->whereNull('client_hidden_at')
                ->whereNull('permanently_deleted_at')
                ->where('upload_status', PhotoUploadStatus::Ready)
                ->with('collection:id,name')
                ->get();

            if ($photos->count() !== count($job->photo_ids ?? [])) {
                throw new RuntimeException('One or more photos are no longer available.');
            }

            if ($job->mode === DownloadMode::Single) {
                $photo = $photos->first();
                $job->forceFill([
                    'status' => DownloadJobStatus::Ready,
                    'file_size_bytes' => $photo->file_size_bytes,
                    'archive_storage_key' => null,
                ])->save();

                return;
            }

            $disk = Storage::disk(config('online-camera.disk'));
            $key = 'downloads/'.$job->client_id.'/'.$job->id.'.zip';
            $temporary = tempnam(sys_get_temp_dir(), 'cczip');

            if ($temporary === false) {
                throw new RuntimeException('The download archive could not be created.');
            }

            $files = [];
            $used = [];
            $index = 1;

            foreach ($photos as $photo) {
                $local = $this->localPath($disk, $photo->storage_key);
                $name = $this->archiveName($photo, $index, $used);
                $files[] = ['name' => $name, 'path' => $local];
                $index++;
            }

            StoredZip::create($temporary, $files);
            $disk->put($key, fopen($temporary, 'rb'));
            @unlink($temporary);

            $job->forceFill([
                'status' => DownloadJobStatus::Ready,
                'archive_storage_key' => $key,
                'file_size_bytes' => $disk->size($key),
            ])->save();
        } catch (\Throwable $exception) {
            $job->forceFill([
                'status' => DownloadJobStatus::Failed,
                'error_message' => 'The download could not be prepared.',
            ])->save();

            report($exception);
        }
    }

    private function singleName(Photo $photo): string
    {
        $base = Str::slug($photo->collection?->name ?: 'photo') ?: 'photo';

        return $base.'-'.substr($photo->id, 0, 8).'.'.$photo->extension();
    }

    /**
     * @param  array<string, true>  $used
     */
    private function archiveName(Photo $photo, int $index, array &$used): string
    {
        $base = Str::slug($photo->collection?->name ?: 'photo') ?: 'photo';
        $name = sprintf('%s-%02d.%s', $base, $index, $photo->extension());
        $candidate = $name;
        $suffix = 2;

        while (isset($used[$candidate])) {
            $candidate = sprintf('%s-%02d-%d.%s', $base, $index, $suffix, $photo->extension());
            $suffix++;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    private function localPath($disk, string $key): string
    {
        if (config('online-camera.disk') !== 'local') {
            $temporary = tempnam(sys_get_temp_dir(), 'ccphoto');

            if ($temporary === false) {
                throw new RuntimeException('A photo could not be prepared for download.');
            }

            file_put_contents($temporary, $disk->get($key));

            return $temporary;
        }

        $path = $disk->path($key);

        if (! is_file($path)) {
            throw new RuntimeException('A photo could not be prepared for download.');
        }

        return $path;
    }
}
