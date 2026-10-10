<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status?->value,
            'must_change_password' => (bool) $this->must_change_password,
            'created_at' => $this->created_at?->toIso8601String(),
            'collections_count' => $this->whenCounted('collections'),
            'photos_count' => $this->whenCounted('photos'),
        ];
    }
}
