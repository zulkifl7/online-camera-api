<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'camera_link_id',
        'idempotency_key',
        'photo_id',
    ];

    public function cameraLink(): BelongsTo
    {
        return $this->belongsTo(CameraLink::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
