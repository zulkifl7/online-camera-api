<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->query('action')))
            ->when($request->filled('actor_type'), fn ($query) => $query->where('actor_type', $request->query('actor_type')))
            ->orderByDesc('created_at')
            ->paginate(30);

        return AuditLogResource::collection($logs);
    }
}
