<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Models\Admin;
use App\Models\CameraLink;
use App\Models\Client;
use App\Models\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ClientProvisioningService
{
    public function __construct(
        private readonly UsernameGenerator $usernames,
        private readonly TemporaryPasswordGenerator $passwords,
        private readonly CameraLinkService $cameraLinks,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{display_name: string, email?: ?string, phone?: ?string}  $data
     * @return array{client: Client, temporary_password: string, collection: Collection, camera_link: CameraLink}
     */
    public function create(Admin $admin, array $data): array
    {
        return DB::transaction(function () use ($admin, $data) {
            $temporary = $this->passwords->generate();

            $client = Client::query()->create([
                'display_name' => $data['display_name'],
                'username' => $this->usernames->generate($data['display_name']),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $temporary,
                'must_change_password' => true,
                'status' => ClientStatus::Active,
                'created_by_admin_id' => $admin->id,
            ]);

            $collection = Collection::query()->create([
                'client_id' => $client->id,
                'type' => CollectionType::Common,
                'name' => 'Common Gallery',
                'status' => CollectionStatus::Active,
                'uploads_enabled' => true,
                'common_guard' => $client->id,
                'created_by_admin_id' => $admin->id,
            ]);

            $link = $this->cameraLinks->create($admin, $client, $collection);

            $this->audit->record(
                request(),
                'admin',
                $admin->id,
                'client.created',
                'client',
                $client->id,
                [
                    'display_name' => $client->display_name,
                    'username' => $client->username,
                    'collection_id' => $collection->id,
                    'camera_link_id' => $link->id,
                ],
            );

            return [
                'client' => $client,
                'temporary_password' => $temporary,
                'collection' => $collection->load('activeCameraLink'),
                'camera_link' => $link,
            ];
        });
    }

    public function resetPassword(Admin $admin, Client $client): string
    {
        $temporary = $this->passwords->generate();

        $client->forceFill([
            'password' => $temporary,
            'must_change_password' => true,
            'auth_version' => $client->auth_version + 1,
        ])->save();

        $this->audit->record(
            request(),
            'admin',
            $admin->id,
            'client.password_reset',
            'client',
            $client->id,
            ['username' => $client->username],
        );

        return $temporary;
    }

    /**
     * @param  array{display_name?: string, email?: ?string, phone?: ?string, status?: string, username?: string}  $data
     */
    public function update(Admin $admin, Client $client, array $data): Client
    {
        return DB::transaction(function () use ($admin, $client, $data) {
            if (array_key_exists('display_name', $data)) {
                $client->display_name = $data['display_name'];
            }

            if (array_key_exists('email', $data)) {
                $client->email = $data['email'];
            }

            if (array_key_exists('phone', $data)) {
                $client->phone = $data['phone'];
            }

            if (array_key_exists('username', $data) && $data['username'] !== null && $data['username'] !== '') {
                $normalized = strtolower(trim($data['username']));
                $normalized = trim((string) preg_replace('/-+/', '-', (string) preg_replace('/[^a-z0-9]+/', '-', $normalized)), '-');

                if ($normalized === '' || strlen($normalized) < 2) {
                    throw ValidationException::withMessages([
                        'username' => 'Enter a username using letters or numbers.',
                    ]);
                }

                $taken = Client::query()->where('username', $normalized)->where('id', '!=', $client->id)->exists();

                if ($taken) {
                    throw ValidationException::withMessages([
                        'username' => 'That username is already in use.',
                    ]);
                }

                $client->username = $normalized;
            }

            if (array_key_exists('status', $data)) {
                $status = ClientStatus::from($data['status']);
                $client->status = $status;
                $client->archived_at = $status === ClientStatus::Archived ? ($client->archived_at ?? now()) : null;

                if ($status !== ClientStatus::Active) {
                    $client->collections()->update(['uploads_enabled' => false]);
                    $client->auth_version++;
                }
            }

            $client->save();

            $this->audit->record(
                request(),
                'admin',
                $admin->id,
                'client.updated',
                'client',
                $client->id,
                ['status' => $client->status->value],
            );

            return $client->refresh();
        });
    }

    public function passwordMatches(Client $client, string $plain): bool
    {
        return Hash::check($plain, $client->password);
    }
}
