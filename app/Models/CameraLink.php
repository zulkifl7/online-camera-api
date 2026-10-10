<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CameraLink extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'collection_id',
        'token_hash',
        'token_encrypted',
        'is_active',
        'expires_at',
        'created_by_admin_id',
        'rotated_from_id',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
        'token_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function rawToken(): ?string
    {
        try {
            $token = decrypt($this->token_encrypted);
        } catch (\Throwable) {
            return null;
        }

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function publicUrl(): ?string
    {
        $token = $this->rawToken();

        if ($token === null) {
            return null;
        }

        return rtrim((string) config('app.frontend_url'), '/').'/camera/'.$token;
    }

    public function statusLabel(): string
    {
        if (! $this->is_active || $this->revoked_at !== null) {
            return 'revoked';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
