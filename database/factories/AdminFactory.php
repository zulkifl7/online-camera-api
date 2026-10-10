<?php

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Support\AdminPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'name' => 'Cyrus Crew Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'role' => AdminRole::SuperAdmin,
            'permissions' => AdminPermission::all(),
            'auth_version' => 1,
        ];
    }

    public function staff(): static
    {
        return $this->state(fn () => [
            'role' => AdminRole::Staff,
            'permissions' => [
                AdminPermission::CLIENTS_READ,
                AdminPermission::PHOTOS_READ,
                AdminPermission::PHOTOS_RESTORE,
            ],
        ]);
    }
}
