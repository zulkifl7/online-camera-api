<?php

namespace App\Http\Resources;

use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'collection_id' => $this->collection_id,
            'collection_name' => $this->collection?->name,
            'client_name' => $this->client?->display_name,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'upload_status' => $this->upload_status?->value,
            'failure_reason' => $this->failure_reason,
            'client_hidden_at' => $this->client_hidden_at?->toIso8601String(),
            'permanently_deleted_at' => $this->permanently_deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'thumbnail_url' => $this->permanently_deleted_at ? null : PublicUrl::to('/api/v1/admin/photos/'.$this->id.'/thumbnail'),
            'preview_url' => $this->permanently_deleted_at ? null : PublicUrl::to('/api/v1/admin/photos/'.$this->id.'/preview'),
        ];
    }
}
