<?php

namespace App\Models;

use App\Enums\DownloadJobStatus;
use App\Enums\DownloadMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'mode',
        'status',
        'photo_ids',
        'file_name',
        'archive_storage_key',
        'file_size_bytes',
        'error_message',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => DownloadMode::class,
            'status' => DownloadJobStatus::class,
            'photo_ids' => 'array',
            'file_size_bytes' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
