<?php

namespace App\Models;

use App\Enums\PhotoUploadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'collection_id',
        'storage_key',
        'thumbnail_storage_key',
        'preview_storage_key',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'width',
        'height',
        'checksum',
        'upload_status',
        'failure_reason',
        'client_hidden_at',
        'client_hidden_by',
        'permanently_deleted_at',
        'permanently_deleted_by',
    ];

    protected $hidden = [
        'storage_key',
        'thumbnail_storage_key',
        'preview_storage_key',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'upload_status' => PhotoUploadStatus::class,
            'file_size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'client_hidden_at' => 'datetime',
            'permanently_deleted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function extension(): string
    {
        return match ($this->mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
