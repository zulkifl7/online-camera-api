<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Support\AdminPermission;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        Admin::query()->updateOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => env('ADMIN_NAME', 'Cyrus Crew Admin'),
                'password' => $password,
                'is_active' => true,
                'role' => AdminRole::SuperAdmin,
                'permissions' => AdminPermission::all(),
            ],
        );
    }
}
