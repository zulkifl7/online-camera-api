<?php

namespace App\Http\Resources;

use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection_id' => $this->collection_id,
            'collection_name' => $this->collection?->name,
            'collection_type' => $this->collection?->type?->value,
            'width' => $this->width,
            'height' => $this->height,
            'created_at' => $this->created_at?->toIso8601String(),
            'thumbnail_url' => PublicUrl::to('/api/v1/client/photos/'.$this->id.'/thumbnail'),
            'preview_url' => PublicUrl::to('/api/v1/client/photos/'.$this->id.'/preview'),
        ];
    }
}
