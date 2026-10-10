<?php

namespace App\Http\Controllers\Client;

use App\Enums\DownloadJobStatus;
use App\Enums\DownloadMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\PhotoIdsRequest;
use App\Http\Resources\DownloadJobResource;
use App\Models\DownloadJob;
use App\Models\Photo;
use App\Services\BulkDownloadService;
use App\Services\PrivateMediaStreamer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function __construct(
        private readonly BulkDownloadService $downloads,
        private readonly PrivateMediaStreamer $media,
    ) {}

    public function store(PhotoIdsRequest $request)
    {
        $job = $this->downloads->queue($request->user('client'), $request->validated('photo_ids'));

        return (new DownloadJobResource($job))->response()->setStatusCode(201);
    }

    public function show(Request $request, DownloadJob $downloadJob)
    {
        $this->authorizeJob($request, $downloadJob);

        return new DownloadJobResource($downloadJob);
    }

    public function file(Request $request, DownloadJob $downloadJob)
    {
        $this->authorizeJob($request, $downloadJob);

        if ($downloadJob->isExpired() || $downloadJob->status !== DownloadJobStatus::Ready) {
            return response()->json([
                'message' => 'This download is no longer available. Prepare it again.',
            ], 410);
        }

        if ($downloadJob->mode === DownloadMode::Single) {
            $photoId = $downloadJob->photo_ids[0] ?? null;
            $photo = Photo::query()->where('client_id', $request->user('client')->id)->findOrFail($photoId);
            Gate::forUser($request->user('client'))->authorize('download', $photo);

            return $this->media->stream($photo, 'original', true, $downloadJob->file_name);
        }

        $key = $downloadJob->archive_storage_key;
        $disk = Storage::disk(config('online-camera.disk'));

        if (! is_string($key) || ! $disk->exists($key)) {
            return response()->json([
                'message' => 'This download is no longer available. Prepare it again.',
            ], 410);
        }

        return $disk->download($key, $downloadJob->file_name ?: 'gallery.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeJob(Request $request, DownloadJob $job): void
    {
        if ($job->client_id !== $request->user('client')->id) {
            abort(404);
        }
    }
}
