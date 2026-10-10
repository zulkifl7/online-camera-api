<?php

namespace App\Services;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Collection;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionService
{
    public function __construct(
        private readonly CameraLinkService $cameraLinks,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{name: string, event_date?: ?string, timezone?: ?string}  $data
     */
    public function createEvent(Admin $admin, Client $client, array $data): Collection
    {
        $name = trim($data['name']);
        $this->assertUniqueName($client, $name);
        $timezone = $this->normalizeTimezone($data['timezone'] ?? null);

        return DB::transaction(function () use ($admin, $client, $data, $name, $timezone) {
            $collection = Collection::query()->create([
                'client_id' => $client->id,
                'type' => CollectionType::Event,
                'name' => $name,
                'event_date' => $data['event_date'] ?? null,
                'timezone' => $timezone,
                'status' => CollectionStatus::Active,
                'uploads_enabled' => $client->status->value === 'active',
                'common_guard' => null,
                'created_by_admin_id' => $admin->id,
            ]);

            $link = $this->cameraLinks->create($admin, $client, $collection);

            $this->audit->record(
                request(),
                'admin',
                $admin->id,
                'collection.created',
                'collection',
                $collection->id,
                [
                    'client_id' => $client->id,
                    'name' => $collection->name,
                    'camera_link_id' => $link->id,
                ],
            );

            return $collection->load('activeCameraLink');
        });
    }

    /**
     * @param  array{name?: string, event_date?: ?string, timezone?: ?string, uploads_enabled?: bool}  $data
     */
    public function update(Admin $admin, Collection $collection, array $data): Collection
    {
        if (array_key_exists('name', $data) && $collection->type === CollectionType::Event) {
            $name = trim((string) $data['name']);
            $this->assertUniqueName($collection->client, $name, $collection->id);
            $collection->name = $name;
        }

        if (array_key_exists('event_date', $data) && $collection->type === CollectionType::Event) {
            $collection->event_date = $data['event_date'];
        }

        if (array_key_exists('timezone', $data)) {
            $collection->timezone = $this->normalizeTimezone($data['timezone']);
        }

        if (array_key_exists('uploads_enabled', $data)) {
            $collection->uploads_enabled = (bool) $data['uploads_enabled'];
        }

        $collection->save();

        $this->audit->record(
            request(),
            'admin',
            $admin->id,
            'collection.updated',
            'collection',
            $collection->id,
            [
                'uploads_enabled' => $collection->uploads_enabled,
                'name' => $collection->name,
            ],
        );

        return $collection->refresh()->load('activeCameraLink');
    }

    public function setUploads(Admin $admin, Collection $collection, bool $enabled): Collection
    {
        if ($enabled && $collection->client->status->value !== 'active') {
            throw ValidationException::withMessages([
                'uploads_enabled' => 'Resume the client account before accepting uploads.',
            ]);
        }

        $collection->uploads_enabled = $enabled;
        $collection->save();

        $this->audit->record(
            request(),
            'admin',
            $admin->id,
            $enabled ? 'camera_link.resumed' : 'camera_link.paused',
            'collection',
            $collection->id,
            ['client_id' => $collection->client_id],
        );

        return $collection->refresh()->load('activeCameraLink');
    }

    private function assertUniqueName(Client $client, string $name, ?string $ignoreId = null): void
    {
        $exists = Collection::query()
            ->where('client_id', $client->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This client already has a collection with that name.',
            ]);
        }
    }

    private function normalizeTimezone(?string $timezone): ?string
    {
        if ($timezone === null || trim($timezone) === '') {
            return null;
        }

        $timezone = trim($timezone);

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages([
                'timezone' => 'Choose a valid timezone.',
            ]);
        }

        return $timezone;
    }
}
