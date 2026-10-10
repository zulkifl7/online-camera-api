<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Collection extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'type',
        'name',
        'event_date',
        'timezone',
        'status',
        'uploads_enabled',
        'cover_mobile_key',
        'cover_desktop_key',
        'common_guard',
        'created_by_admin_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'status' => CollectionStatus::class,
            'uploads_enabled' => 'boolean',
            'event_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function cameraLinks(): HasMany
    {
        return $this->hasMany(CameraLink::class);
    }

    public function activeCameraLink(): HasOne
    {
        return $this->hasOne(CameraLink::class)->where('is_active', true)->latestOfMany();
    }

    public function qrLabel(): string
    {
        if ($this->type === CollectionType::Common) {
            return 'Common Camera QR — Uploads to Common Gallery';
        }

        return $this->name.' Camera QR — Uploads to '.$this->name;
    }

    public function cameraHeading(): string
    {
        return $this->type === CollectionType::Common ? 'Common Photos' : $this->name;
    }
}
