<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientStatus;
use App\Enums\PhotoUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\AdminCollectionResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Photo;
use App\Services\ClientProvisioningService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(private readonly ClientProvisioningService $clients) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $clients = Client::query()
            ->withCount(['collections', 'photos'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('display_name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request)
    {
        $result = $this->clients->create($request->user('admin'), $request->validated());
        $link = $result['camera_link'];

        return response()->json([
            'data' => [
                'client' => (new ClientResource($result['client']))->resolve($request),
                'temporary_password' => $result['temporary_password'],
                'common_camera_url' => $link->publicUrl(),
                'common_camera_label' => 'Common Camera QR — Uploads to Common Gallery',
                'client_login_url' => rtrim((string) config('app.frontend_url'), '/').'/client/login',
                'client_login_label' => 'Client Login QR — Opens Client Login',
            ],
        ], 201);
    }

    public function show(Request $request, Client $client)
    {
        $client->loadCount(['collections', 'photos']);
        $collections = $client->collections()
            ->with('activeCameraLink')
            ->withCount([
                'photos as visible_photos_count' => function ($query) {
                    $query->whereNull('client_hidden_at')
                        ->whereNull('permanently_deleted_at')
                        ->where('upload_status', PhotoUploadStatus::Ready->value);
                },
            ])
            ->orderByRaw("case when type = 'common' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'client' => (new ClientResource($client))->resolve($request),
                'collections' => AdminCollectionResource::collection($collections)->resolve($request),
                'client_login_url' => rtrim((string) config('app.frontend_url'), '/').'/client/login',
                'client_login_label' => 'Client Login QR — Opens Client Login',
            ],
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $updated = $this->clients->update($request->user('admin'), $client, $request->validated());

        return new ClientResource($updated);
    }

    public function resetPassword(Request $request, Client $client)
    {
        if ($client->status === ClientStatus::Archived) {
            return response()->json([
                'message' => 'Archived clients cannot receive a new password.',
            ], 422);
        }

        $temporary = $this->clients->resetPassword($request->user('admin'), $client);

        return response()->json([
            'data' => [
                'username' => $client->username,
                'temporary_password' => $temporary,
                'must_change_password' => true,
            ],
        ]);
    }

    public function overview()
    {
        return response()->json([
            'data' => [
                'clients' => Client::query()->count(),
                'active_clients' => Client::query()->where('status', ClientStatus::Active)->count(),
                'photos' => Photo::query()->whereNull('permanently_deleted_at')->count(),
                'hidden_photos' => Photo::query()->whereNotNull('client_hidden_at')->whereNull('permanently_deleted_at')->count(),
                'failed_photos' => Photo::query()->where('upload_status', PhotoUploadStatus::Failed)->count(),
                'processing_photos' => Photo::query()->whereIn('upload_status', [
                    PhotoUploadStatus::Pending,
                    PhotoUploadStatus::Processing,
                ])->count(),
            ],
        ]);
    }
}
