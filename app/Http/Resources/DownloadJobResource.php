<?php

namespace App\Http\Resources;

use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DownloadJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode?->value,
            'status' => $this->status?->value,
            'file_name' => $this->file_name,
            'file_size_bytes' => $this->file_size_bytes,
            'photo_count' => is_array($this->photo_ids) ? count($this->photo_ids) : 0,
            'error_message' => $this->error_message,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'download_url' => $this->status?->value === 'ready'
                ? PublicUrl::to('/api/v1/client/downloads/'.$this->id.'/file')
                : null,
        ];
    }
}
