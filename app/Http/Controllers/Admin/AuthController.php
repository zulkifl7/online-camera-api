<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Admin;
use App\Support\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = [
            'email' => strtolower(trim((string) $request->input('email'))),
            'password' => (string) $request->input('password'),
            'is_active' => true,
        ];

        if (! Auth::guard('admin')->attempt($credentials)) {
            return response()->json([
                'message' => 'Those credentials do not match our records.',
            ], 401);
        }

        $request->session()->regenerate();

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $admin->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('auth_version.admin', $admin->auth_version);

        return response()->json(['data' => $this->payload($admin)]);
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->forget('auth_version.admin');
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json([
            'data' => $this->payload($request->user('admin')),
        ]);
    }

    private function payload(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role?->value,
            'permissions' => $admin->permissions ?? AdminPermission::all(),
        ];
    }
}
