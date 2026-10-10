<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PhotoIdsRequest;
use App\Http\Resources\AdminPhotoResource;
use App\Models\Photo;
use App\Services\PhotoVisibilityService;
use App\Services\PrivateMediaStreamer;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct(
        private readonly PhotoVisibilityService $visibility,
        private readonly PrivateMediaStreamer $media,
    ) {}

    public function index(Request $request)
    {
        $photos = Photo::query()
            ->with(['collection:id,name,type', 'client:id,display_name'])
            ->whereNull('permanently_deleted_at')
            ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->query('client_id')))
            ->when($request->filled('collection_id'), fn ($query) => $query->where('collection_id', $request->query('collection_id')))
            ->when($request->filled('upload_status'), fn ($query) => $query->where('upload_status', $request->query('upload_status')))
            ->when($request->query('visibility') === 'visible', fn ($query) => $query->whereNull('client_hidden_at'))
            ->orderByDesc('created_at')
            ->paginate(24);

        return AdminPhotoResource::collection($photos);
    }

    public function hidden(Request $request)
    {
        $photos = Photo::query()
            ->with(['collection:id,name,type', 'client:id,display_name'])
            ->whereNotNull('client_hidden_at')
            ->whereNull('permanently_deleted_at')
            ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->query('client_id')))
            ->when($request->filled('collection_id'), fn ($query) => $query->where('collection_id', $request->query('collection_id')))
            ->orderByDesc('client_hidden_at')
            ->paginate(24);

        return AdminPhotoResource::collection($photos);
    }

    public function bulkRestore(PhotoIdsRequest $request)
    {
        $clientId = $request->validate([
            'client_id' => ['required', 'uuid'],
        ])['client_id'];

        $restored = $this->visibility->restoreMany(
            $request->user('admin'),
            $clientId,
            $request->validated('photo_ids'),
        );

        return response()->json([
            'data' => ['restored' => $restored],
        ]);
    }

    public function bulkDestroy(PhotoIdsRequest $request)
    {
        $clientId = $request->validate([
            'client_id' => ['required', 'uuid'],
            'confirm' => ['required', 'accepted'],
        ])['client_id'];

        $deleted = $this->visibility->permanentlyDeleteMany(
            $request->user('admin'),
            $clientId,
            $request->validated('photo_ids'),
        );

        return response()->json([
            'data' => ['deleted' => $deleted],
        ]);
    }

    public function restore(Request $request, Photo $photo)
    {
        return new AdminPhotoResource(
            $this->visibility->restore($request->user('admin'), $photo)->load(['collection:id,name,type', 'client:id,display_name']),
        );
    }

    public function destroy(Request $request, Photo $photo)
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        $this->visibility->permanentlyDelete($request->user('admin'), $photo);

        return response()->json([
            'data' => [
                'id' => $photo->id,
                'permanently_deleted_at' => $photo->fresh()->permanently_deleted_at?->toIso8601String(),
            ],
        ]);
    }

    public function thumbnail(Photo $photo)
    {
        return $this->media->stream($photo, 'thumbnail');
    }

    public function preview(Photo $photo)
    {
        return $this->media->stream($photo, 'preview');
    }
}
