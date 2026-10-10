<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?Request $request,
        string $actorType,
        ?string $actorId,
        string $action,
        ?string $targetType,
        ?string $targetId,
        array $metadata = [],
    ): AuditLog {
        $userAgent = $request?->userAgent();

        return AuditLog::query()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => ($clean = $this->scrub($metadata)) === [] ? null : $clean,
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent ? Str::limit($userAgent, 500, '') : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function scrub(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $lower = strtolower((string) $key);

            if (
                str_contains($lower, 'password')
                || str_contains($lower, 'token')
                || str_contains($lower, 'secret')
            ) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }
}
