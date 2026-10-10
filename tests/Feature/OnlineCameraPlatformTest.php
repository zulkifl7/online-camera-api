<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Photo;
use App\Support\AdminPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnlineCameraPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_client_creation_provisions_one_common_gallery_and_never_stores_plaintext_password(): void
    {
        $admin = $this->signInAdmin();

        $response = $this->postJson('/api/v1/admin/clients', [
            'display_name' => 'Alex & Jamie!',
            'email' => 'couple@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.client.username', 'alex-jamie')
            ->assertJsonPath('data.common_camera_label', 'Common Camera QR — Uploads to Common Gallery');

        $password = $response->json('data.temporary_password');
        $this->assertMatchesRegularExpression('/^\d{8}$/', $password);

        $client = Client::query()->firstOrFail();
        $this->assertTrue(Hash::check($password, $client->password));
        $this->assertNotSame($password, $client->password);
        $this->assertTrue($client->must_change_password);
        $this->assertSame(1, $client->collections()->where('type', 'common')->count());
        $this->assertSame(1, $client->cameraLinks()->where('is_active', true)->count());

        $url = $response->json('data.common_camera_url');
        $this->assertStringNotContainsString($password, $url);
        $this->assertStringContainsString('/camera/', $url);

        $this->getJson('/api/v1/admin/clients/'.$client->id)
            ->assertOk()
            ->assertJsonMissingPath('data.temporary_password');

        $audit = AuditLog::query()->where('action', 'client.created')->firstOrFail();
        $this->assertStringNotContainsString($password, json_encode($audit->metadata));
        $this->assertStringNotContainsString($this->tokenFromUrl($url), json_encode($audit->toArray()));

        $this->assertTrue($admin->hasPermission(AdminPermission::CLIENTS_WRITE));
    }

    public function test_username_collisions_receive_a_numeric_suffix(): void
    {
        $this->signInAdmin();

        $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();
        $second = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();

        $second->assertJsonPath('data.client.username', 'alex-and-jamie-2');
        $this->assertSame(2, Client::query()->count());
    }

    public function test_client_credentials_cannot_open_admin_endpoints(): void
    {
        $this->signInAdmin();
        $created = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();

        $this->postJson('/api/v1/admin/logout')->assertNoContent();

        $this->postJson('/api/v1/client/login', [
            'username' => 'alex-and-jamie',
            'password' => $created->json('data.temporary_password'),
        ])->assertOk();

        $this->getJson('/api/v1/admin/clients')->assertUnauthorized();
        $this->postJson('/api/v1/admin/login', [
            'email' => 'alex-and-jamie',
            'password' => $created->json('data.temporary_password'),
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Those credentials do not match our records.');
    }

    public function test_camera_pages_accept_uploads_only_for_the_linked_collection(): void
    {
        $this->signInAdmin();
        $created = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();
        $clientId = $created->json('data.client.id');
        $commonToken = $this->tokenFromUrl($created->json('data.common_camera_url'));

        $event = $this->postJson('/api/v1/admin/clients/'.$clientId.'/events', [
            'name' => 'Wedding',
            'event_date' => '2026-12-01',
        ])->assertCreated();

        $event->assertJsonPath('data.qr_label', 'Wedding Camera QR — Uploads to Wedding');
        $eventToken = $this->tokenFromUrl($event->json('data.camera_link.url'));

        $this->getJson('/api/v1/public/camera/'.$commonToken)
            ->assertOk()
            ->assertJsonPath('data.heading', 'Common Photos')
            ->assertJsonPath('data.kind', 'common')
            ->assertJsonPath('data.client_name', 'Alex and Jamie')
            ->assertJsonPath('data.upload_note', "Photos from this link are uploaded to Alex and Jamie's Event Gallery.")
            ->assertJsonMissingPath('data.photos');

        $this->getJson('/api/v1/public/camera/not-a-real-token')->assertNotFound();

        $commonUpload = $this->postPhoto($commonToken, 'idem-common-1');
        $eventUpload = $this->postPhoto($eventToken, 'idem-event-1');
        $duplicate = $this->postPhoto($eventToken, 'idem-event-1');

        $commonUpload->assertCreated()->assertJsonPath('data.status', 'ready');
        $eventUpload->assertCreated();
        $duplicate->assertCreated()->assertJsonPath('data.id', $eventUpload->json('data.id'));

        $this->assertSame(2, Photo::query()->count());

        $commonCollection = Collection::query()->where('type', 'common')->firstOrFail();
        $eventCollection = Collection::query()->where('name', 'Wedding')->firstOrFail();

        $this->assertSame(1, Photo::query()->where('collection_id', $commonCollection->id)->count());
        $this->assertSame(1, Photo::query()->where('collection_id', $eventCollection->id)->count());
        $this->assertSame($clientId, Photo::query()->first()->client_id);

        $publicBody = $eventUpload->json();
        $this->assertArrayNotHasKey('storage_key', $publicBody['data']);
        $this->assertArrayNotHasKey('thumbnail_url', $publicBody['data']);
    }

    public function test_paused_and_rotated_links_stop_new_uploads_without_deleting_photos(): void
    {
        $this->signInAdmin();
        $created = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();
        $token = $this->tokenFromUrl($created->json('data.common_camera_url'));
        $this->postPhoto($token, 'idem-keep')->assertCreated();

        $collection = Collection::query()->where('type', 'common')->firstOrFail();
        $this->postJson('/api/v1/admin/collections/'.$collection->id.'/pause-uploads')->assertOk();

        $this->getJson('/api/v1/public/camera/'.$token)
            ->assertOk()
            ->assertJsonPath('data.uploads_enabled', false);

        $this->postPhoto($token, 'idem-paused')->assertForbidden();
        $this->assertSame(1, Photo::query()->count());

        $this->postJson('/api/v1/admin/collections/'.$collection->id.'/resume-uploads')->assertOk();

        $linkId = $collection->activeCameraLink()->firstOrFail()->id;
        $rotated = $this->postJson('/api/v1/admin/camera-links/'.$linkId.'/rotate')->assertCreated();
        $newToken = $this->tokenFromUrl($rotated->json('data.url'));

        $this->postPhoto($token, 'idem-old')->assertNotFound();
        $this->postPhoto($newToken, 'idem-new')->assertCreated();
        $this->assertSame(2, Photo::query()->count());
    }

    public function test_invalid_files_are_rejected(): void
    {
        $this->signInAdmin();
        $created = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();
        $token = $this->tokenFromUrl($created->json('data.common_camera_url'));

        $path = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($path, 'this is not an image');
        $file = new UploadedFile($path, 'notes.txt', 'text/plain', null, true);

        $this->post('/api/v1/public/camera/'.$token.'/photos', ['photo' => $file], [
            'Accept' => 'application/json',
        ])->assertStatus(422);

        $this->assertSame(0, Photo::query()->count());
    }

    public function test_admin_can_set_mobile_and_desktop_covers_for_a_camera_page(): void
    {
        $this->signInAdmin();
        $created = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();
        $clientId = $created->json('data.client.id');
        $collectionId = $this->getJson('/api/v1/admin/clients/'.$clientId)->assertOk()->json('data.collections.0.id');
        $token = $this->tokenFromUrl($created->json('data.common_camera_url'));

        $uploaded = $this->post('/api/v1/admin/collections/'.$collectionId.'/covers', [
            'mobile' => $this->pngUpload(),
            'desktop' => $this->pngUpload(),
        ], ['Accept' => 'application/json']);

        $uploaded->assertOk();
        $this->assertStringContainsString('/covers/mobile', (string) $uploaded->json('data.cover_mobile_url'));
        $this->assertStringContainsString('/covers/desktop', (string) $uploaded->json('data.cover_desktop_url'));

        $this->get('/api/v1/public/camera/'.$token.'/covers/mobile')->assertOk();
        $this->get('/api/v1/public/camera/'.$token.'/covers/desktop')->assertOk();
        $this->getJson('/api/v1/public/camera/'.$token)
            ->assertOk()
            ->assertJsonPath('data.cover_mobile_url', fn ($url) => is_string($url) && str_contains($url, '/covers/mobile'));
    }

    public function test_clients_only_see_their_visible_photos_and_hiding_keeps_the_original(): void
    {
        $admin = $this->signInAdmin();
        $first = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();
        $second = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Sam and Riley'])->assertCreated();

        $token = $this->tokenFromUrl($first->json('data.common_camera_url'));
        $upload = $this->postPhoto($token, 'idem-gallery');
        $photoId = $upload->json('data.id');
        $photo = Photo::query()->findOrFail($photoId);
        Storage::disk('local')->assertExists($photo->storage_key);

        $this->postJson('/api/v1/admin/logout')->assertNoContent();

        $this->postJson('/api/v1/client/login', [
            'username' => $first->json('data.client.username'),
            'password' => $first->json('data.temporary_password'),
        ])->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->getJson('/api/v1/client/collections')->assertForbidden()
            ->assertJsonPath('code', 'password_change_required');

        $this->postJson('/api/v1/client/change-password', [
            'current_password' => $first->json('data.temporary_password'),
            'password' => 'better-password',
            'password_confirmation' => 'better-password',
        ])->assertOk()->assertJsonPath('data.must_change_password', false);

        $this->getJson('/api/v1/client/collections')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'common')
            ->assertJsonPath('data.0.visible_photo_count', 1);

        $this->getJson('/api/v1/client/photos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $photoId)
            ->assertJsonPath('data.0.collection_name', 'Common Gallery');

        $otherCollection = Client::query()->where('username', $second->json('data.client.username'))->firstOrFail()
            ->collections()->firstOrFail();

        $this->getJson('/api/v1/client/collections/'.$otherCollection->id.'/photos')->assertNotFound();
        $this->postJson('/api/v1/client/downloads', [
            'photo_ids' => [$photoId, (string) $otherCollection->id],
        ])->assertStatus(422);

        $this->postJson('/api/v1/client/photos/bulk-hide', ['photo_ids' => [$photoId]])->assertOk();
        $this->getJson('/api/v1/client/photos')->assertOk()->assertJsonCount(0, 'data');
        $this->assertNotNull($photo->fresh()->client_hidden_at);
        $this->assertNull($photo->fresh()->permanently_deleted_at);
        Storage::disk('local')->assertExists($photo->storage_key);

        $this->signInAdmin($admin);
        $this->postJson('/api/v1/admin/photos/'.$photoId.'/restore')->assertOk();
        $this->assertNull($photo->fresh()->client_hidden_at);
    }

    public function test_admin_can_restore_or_delete_selected_deleted_photos(): void
    {
        $admin = $this->signInAdmin();
        $created = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();
        $clientId = $created->json('data.client.id');
        $token = $this->tokenFromUrl($created->json('data.common_camera_url'));
        $first = $this->postPhoto($token, 'idem-bulk-a')->json('data.id');
        $second = $this->postPhoto($token, 'idem-bulk-b')->json('data.id');

        $this->postJson('/api/v1/admin/logout')->assertNoContent();
        $this->postJson('/api/v1/client/login', [
            'username' => $created->json('data.client.username'),
            'password' => $created->json('data.temporary_password'),
        ])->assertOk();
        $this->postJson('/api/v1/client/change-password', [
            'current_password' => $created->json('data.temporary_password'),
            'password' => 'better-password',
            'password_confirmation' => 'better-password',
        ])->assertOk();
        $this->postJson('/api/v1/client/photos/bulk-hide', ['photo_ids' => [$first, $second]])->assertOk();

        $this->signInAdmin($admin);
        $this->postJson('/api/v1/admin/photos/bulk-restore', [
            'client_id' => $clientId,
            'photo_ids' => [$first],
        ])->assertOk()->assertJsonPath('data.restored', 1);
        $this->assertNull(Photo::query()->findOrFail($first)->client_hidden_at);

        $this->deleteJson('/api/v1/admin/photos/bulk', [
            'client_id' => $clientId,
            'photo_ids' => [$second],
        ])->assertStatus(422);

        $this->deleteJson('/api/v1/admin/photos/bulk', [
            'client_id' => $clientId,
            'photo_ids' => [$second],
            'confirm' => true,
        ])->assertOk()->assertJsonPath('data.deleted', 1);
        $this->assertNotNull(Photo::query()->findOrFail($second)->permanently_deleted_at);
    }

    public function test_permanent_deletion_requires_permission_and_removes_media(): void
    {
        $admin = $this->signInAdmin();
        $created = $this->postJson('/api/v1/admin/clients', ['display_name' => 'Alex and Jamie'])->assertCreated();
        $token = $this->tokenFromUrl($created->json('data.common_camera_url'));
        $photoId = $this->postPhoto($token, 'idem-delete')->json('data.id');
        $photo = Photo::query()->findOrFail($photoId);
        $originalKey = $photo->storage_key;

        $staff = Admin::factory()->staff()->create();
        $this->signInAdmin($staff);
        $this->deleteJson('/api/v1/admin/photos/'.$photoId, ['confirm' => true])->assertForbidden();
        Storage::disk('local')->assertExists($originalKey);

        $this->signInAdmin($admin);
        $this->deleteJson('/api/v1/admin/photos/'.$photoId, ['confirm' => true])->assertOk();

        $this->assertNotNull($photo->fresh()->permanently_deleted_at);
        Storage::disk('local')->assertMissing($originalKey);
        $this->assertDatabaseHas('audit_logs', ['action' => 'photo.permanently_deleted']);
    }

    private function signInAdmin(?Admin $admin = null): Admin
    {
        $admin ??= Admin::factory()->create();

        $this->actingAs($admin, 'admin')->withSession([
            'auth_version.admin' => $admin->auth_version,
        ]);

        return $admin;
    }

    private function postPhoto(string $token, string $idempotencyKey)
    {
        return $this->post('/api/v1/public/camera/'.$token.'/photos', [
            'photo' => $this->pngUpload(),
        ], [
            'Accept' => 'application/json',
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }

    private function pngUpload(): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $path = tempnam(sys_get_temp_dir(), 'cc');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'moment.png', 'image/png', null, true);
    }

    private function tokenFromUrl(string $url): string
    {
        return basename((string) parse_url($url, PHP_URL_PATH));
    }
}
