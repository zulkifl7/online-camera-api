<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\PhotoIdsRequest;
use App\Models\Photo;
use App\Services\GalleryQueryService;
use App\Services\PhotoVisibilityService;
use App\Services\PrivateMediaStreamer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PhotoController extends Controller
{
    public function __construct(
        private readonly GalleryQueryService $galleries,
        private readonly PhotoVisibilityService $visibility,
        private readonly PrivateMediaStreamer $media,
    ) {}

    public function hide(Request $request, string $photo)
    {
        $owned = Photo::query()
            ->where('client_id', $request->user('client')->id)
            ->where('id', $photo)
            ->firstOrFail();

        Gate::forUser($request->user('client'))->authorize('hide', $owned);
        $this->visibility->hide($request->user('client'), [$owned->id]);

        return response()->json(['data' => ['hidden' => 1]]);
    }

    public function bulkHide(PhotoIdsRequest $request)
    {
        $count = $this->visibility->hide($request->user('client'), $request->validated('photo_ids'));

        return response()->json(['data' => ['hidden' => $count]]);
    }

    public function thumbnail(Request $request, string $photo)
    {
        $owned = $this->galleries->visiblePhoto($request->user('client'), $photo);

        return $this->media->stream($owned, 'thumbnail');
    }

    public function preview(Request $request, string $photo)
    {
        $owned = $this->galleries->visiblePhoto($request->user('client'), $photo);

        return $this->media->stream($owned, 'preview');
    }
}
