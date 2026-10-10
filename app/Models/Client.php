<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Client extends Authenticatable
{
    use HasUuids;

    protected $fillable = [
        'display_name',
        'username',
        'email',
        'phone',
        'password',
        'must_change_password',
        'status',
        'created_by_admin_id',
        'auth_version',
        'last_login_at',
        'archived_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'status' => ClientStatus::class,
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function cameraLinks(): HasMany
    {
        return $this->hasMany(CameraLink::class);
    }
}
