<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\Client;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function login(LoginRequest $request)
    {
        $username = strtolower(trim((string) $request->input('username')));

        $credentials = [
            'username' => $username,
            'password' => (string) $request->input('password'),
            'status' => ClientStatus::Active->value,
        ];

        if (! Auth::guard('client')->attempt($credentials)) {
            return response()->json([
                'message' => 'Those credentials do not match our records.',
            ], 401);
        }

        $request->session()->regenerate();

        /** @var Client $client */
        $client = Auth::guard('client')->user();
        $client->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('auth_version.client', $client->auth_version);

        return response()->json(['data' => $this->payload($client)]);
    }

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        $request->session()->forget('auth_version.client');
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json([
            'data' => $this->payload($request->user('client')),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        /** @var Client $client */
        $client = $request->user('client');

        if (! Hash::check($request->string('current_password')->toString(), $client->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $client->forceFill([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
            'auth_version' => $client->auth_version + 1,
        ])->save();

        $request->session()->put('auth_version.client', $client->auth_version);

        $this->audit->record($request, 'client', $client->id, 'client.password_changed', 'client', $client->id);

        return response()->json(['data' => $this->payload($client->refresh())]);
    }

    private function payload(Client $client): array
    {
        return [
            'id' => $client->id,
            'display_name' => $client->display_name,
            'username' => $client->username,
            'must_change_password' => (bool) $client->must_change_password,
        ];
    }
}
