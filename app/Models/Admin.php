<?php

namespace App\Models;

use App\Enums\AdminRole;
use App\Support\AdminPermission;
use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
        'permissions',
        'auth_version',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'role' => AdminRole::class,
            'permissions' => 'array',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function permissionList(array $permissions): array
    {
        return array_values(array_intersect(AdminPermission::all(), $permissions));
    }
}
