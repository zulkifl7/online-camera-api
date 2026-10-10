<?php

namespace App\Http\Resources;

use App\Enums\PhotoUploadStatus;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $visible = $this->relationLoaded('photos')
            ? $this->photos->whereNull('client_hidden_at')->whereNull('permanently_deleted_at')->where('upload_status', PhotoUploadStatus::Ready)->count()
            : ($this->visible_photos_count ?? null);

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'type' => $this->type?->value,
            'name' => $this->name,
            'event_date' => $this->event_date?->toDateString(),
            'timezone' => $this->timezone,
            'status' => $this->status?->value,
            'uploads_enabled' => (bool) $this->uploads_enabled,
            'qr_label' => $this->qrLabel(),
            'camera_heading' => $this->cameraHeading(),
            'cover_mobile_url' => $this->cover_mobile_key
                ? PublicUrl::to('/api/v1/admin/collections/'.$this->id.'/covers/mobile')
                : null,
            'cover_desktop_url' => $this->cover_desktop_key
                ? PublicUrl::to('/api/v1/admin/collections/'.$this->id.'/covers/desktop')
                : null,
            'visible_photo_count' => $visible,
            'camera_link' => $this->when(
                $this->relationLoaded('activeCameraLink') && $this->activeCameraLink,
                fn () => new CameraLinkResource($this->activeCameraLink),
            ),
        ];
    }
}
