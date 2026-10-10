<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->user('client');

        if ($client instanceof Client && $client->must_change_password) {
            return response()->json([
                'message' => 'Change the temporary password before continuing.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
