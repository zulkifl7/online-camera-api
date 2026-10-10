<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'name' => $this->type?->value === 'common' ? 'Common Gallery' : $this->name,
            'event_date' => $this->event_date?->toDateString(),
            'visible_photo_count' => $this->visible_photos_count ?? 0,
            'cover_photo_id' => $this->cover_photo_id ?? null,
        ];
    }
}
