<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\CameraLink;
use App\Models\Client;
use App\Models\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CameraLinkService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function create(?Admin $admin, Client $client, Collection $collection, ?string $rotatedFromId = null): CameraLink
    {
        $token = $this->generateToken();

        return CameraLink::query()->create([
            'client_id' => $client->id,
            'collection_id' => $collection->id,
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => encrypt($token),
            'is_active' => true,
            'created_by_admin_id' => $admin?->id,
            'rotated_from_id' => $rotatedFromId,
        ]);
    }

    public function rotate(Admin $admin, CameraLink $link): CameraLink
    {
        return DB::transaction(function () use ($admin, $link) {
            $link->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
            ])->save();

            $collection = $link->collection()->firstOrFail();
            $client = $link->client()->firstOrFail();
            $replacement = $this->create($admin, $client, $collection, $link->id);

            $this->audit->record(
                request(),
                'admin',
                $admin->id,
                'camera_link.rotated',
                'camera_link',
                $replacement->id,
                [
                    'client_id' => $client->id,
                    'collection_id' => $collection->id,
                    'previous_link_id' => $link->id,
                ],
            );

            return $replacement;
        });
    }

    public function resolveActive(string $rawToken): CameraLink
    {
        $link = CameraLink::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if ($link === null || ($link->expires_at !== null && $link->expires_at->isPast())) {
            abort(404, 'This camera link is not available.');
        }

        return $link;
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
