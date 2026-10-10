<?php

namespace App\Http\Middleware;

use App\Enums\ClientStatus;
use App\Models\Admin;
use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthVersion
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        $user = $request->user($guard);

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user instanceof Admin && ! $user->is_active) {
            Auth::guard($guard)->logout();
            abort(401, 'Unauthenticated.');
        }

        if ($user instanceof Client && $user->status !== ClientStatus::Active) {
            Auth::guard($guard)->logout();
            abort(401, 'Unauthenticated.');
        }

        $version = $request->session()->get('auth_version.'.$guard);

        if ((int) $version !== (int) $user->auth_version) {
            Auth::guard($guard)->logout();
            abort(401, 'Your session is no longer valid. Sign in again.');
        }

        return $next($request);
    }
}
