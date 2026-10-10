<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CameraLinkController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\PhotoController as AdminPhotoController;
use App\Http\Controllers\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Client\DownloadController;
use App\Http\Controllers\Client\GalleryController;
use App\Http\Controllers\Client\PhotoController as ClientPhotoController;
use App\Http\Controllers\Guest\CameraController;
use App\Support\AdminPermission;
use Illuminate\Support\Facades\Route;

Route::get('/csrf-cookie', function () {
    return response()->noContent();
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware(['auth:admin', 'auth.version:admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::get('/overview', [ClientController::class, 'overview'])->middleware('permission:'.AdminPermission::CLIENTS_READ);

        Route::get('/clients', [ClientController::class, 'index'])->middleware('permission:'.AdminPermission::CLIENTS_READ);
        Route::post('/clients', [ClientController::class, 'store'])->middleware('permission:'.AdminPermission::CLIENTS_WRITE);
        Route::get('/clients/{client}', [ClientController::class, 'show'])->middleware('permission:'.AdminPermission::CLIENTS_READ);
        Route::patch('/clients/{client}', [ClientController::class, 'update'])->middleware('permission:'.AdminPermission::CLIENTS_WRITE);
        Route::post('/clients/{client}/reset-password', [ClientController::class, 'resetPassword'])->middleware('permission:'.AdminPermission::CLIENTS_WRITE);
        Route::post('/clients/{client}/events', [CollectionController::class, 'storeEvent'])->middleware('permission:'.AdminPermission::COLLECTIONS_WRITE);

        Route::get('/collections/{collection}', [CollectionController::class, 'show'])->middleware('permission:'.AdminPermission::CLIENTS_READ);
        Route::patch('/collections/{collection}', [CollectionController::class, 'update'])->middleware('permission:'.AdminPermission::COLLECTIONS_WRITE);
        Route::post('/collections/{collection}/pause-uploads', [CollectionController::class, 'pause'])->middleware('permission:'.AdminPermission::COLLECTIONS_WRITE);
        Route::post('/collections/{collection}/resume-uploads', [CollectionController::class, 'resume'])->middleware('permission:'.AdminPermission::COLLECTIONS_WRITE);
        Route::post('/collections/{collection}/covers', [CollectionController::class, 'storeCover'])->middleware('permission:'.AdminPermission::COLLECTIONS_WRITE);
        Route::get('/collections/{collection}/covers/{variant}', [CollectionController::class, 'cover'])->where('variant', 'mobile|desktop')->middleware('permission:'.AdminPermission::CLIENTS_READ);
        Route::get('/collections/{collection}/photos', [CollectionController::class, 'photos'])->middleware('permission:'.AdminPermission::PHOTOS_READ);

        Route::post('/camera-links/{link}/rotate', [CameraLinkController::class, 'rotate'])->middleware('permission:'.AdminPermission::COLLECTIONS_WRITE);

        Route::get('/photos', [AdminPhotoController::class, 'index'])->middleware('permission:'.AdminPermission::PHOTOS_READ);
        Route::get('/photos/hidden', [AdminPhotoController::class, 'hidden'])->middleware('permission:'.AdminPermission::PHOTOS_READ);
        Route::post('/photos/bulk-restore', [AdminPhotoController::class, 'bulkRestore'])->middleware('permission:'.AdminPermission::PHOTOS_RESTORE);
        Route::delete('/photos/bulk', [AdminPhotoController::class, 'bulkDestroy'])->middleware('permission:'.AdminPermission::PHOTOS_PERMANENTLY_DELETE);
        Route::post('/photos/{photo}/restore', [AdminPhotoController::class, 'restore'])->middleware('permission:'.AdminPermission::PHOTOS_RESTORE);
        Route::delete('/photos/{photo}', [AdminPhotoController::class, 'destroy'])->middleware('permission:'.AdminPermission::PHOTOS_PERMANENTLY_DELETE);
        Route::get('/photos/{photo}/thumbnail', [AdminPhotoController::class, 'thumbnail'])->middleware('permission:'.AdminPermission::PHOTOS_READ);
        Route::get('/photos/{photo}/preview', [AdminPhotoController::class, 'preview'])->middleware('permission:'.AdminPermission::PHOTOS_READ);

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:'.AdminPermission::AUDIT_LOGS_READ);
    });
});

Route::prefix('public')->group(function () {
    Route::get('/camera/{token}', [CameraController::class, 'show'])->where('token', '[A-Za-z0-9\-_]+');
    Route::get('/camera/{token}/covers/{variant}', [CameraController::class, 'cover'])->where(['token' => '[A-Za-z0-9\-_]+', 'variant' => 'mobile|desktop']);
    Route::post('/camera/{token}/photos', [CameraController::class, 'store'])
        ->middleware('throttle:camera-upload')
        ->where('token', '[A-Za-z0-9\-_]+');
});

Route::prefix('client')->group(function () {
    Route::post('/login', [ClientAuthController::class, 'login'])->middleware('throttle:client-login');

    Route::middleware(['auth:client', 'auth.version:client'])->group(function () {
        Route::post('/logout', [ClientAuthController::class, 'logout']);
        Route::get('/me', [ClientAuthController::class, 'me']);
        Route::post('/change-password', [ClientAuthController::class, 'changePassword']);

        Route::middleware('client.password')->group(function () {
            Route::get('/collections', [GalleryController::class, 'collections']);
            Route::get('/collections/{collection}/photos', [GalleryController::class, 'collectionPhotos']);
            Route::get('/photos', [GalleryController::class, 'photos']);
            Route::post('/photos/{photo}/hide', [ClientPhotoController::class, 'hide']);
            Route::post('/photos/bulk-hide', [ClientPhotoController::class, 'bulkHide']);
            Route::get('/photos/{photo}/thumbnail', [ClientPhotoController::class, 'thumbnail']);
            Route::get('/photos/{photo}/preview', [ClientPhotoController::class, 'preview']);
            Route::post('/downloads', [DownloadController::class, 'store']);
            Route::get('/downloads/{downloadJob}', [DownloadController::class, 'show']);
            Route::get('/downloads/{downloadJob}/file', [DownloadController::class, 'file']);
        });
    });
});
