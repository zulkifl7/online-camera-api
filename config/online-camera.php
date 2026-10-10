<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private media disk
    |--------------------------------------------------------------------------
    |
    | Originals, thumbnails, previews, and temporary ZIP archives are stored
    | on this disk. The local disk is private. Point this at an S3-compatible
    | disk in production and keep the bucket private.
    |
    */

    'disk' => env('MEDIA_DISK', 'local'),

    'max_upload_kilobytes' => (int) env('PHOTO_MAX_UPLOAD_KB', 15360),

    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],

    'max_edge_pixels' => 12000,

    'thumbnail_max' => 480,

    'preview_max' => 1600,

    'max_bulk_photos' => 100,

    'max_bulk_bytes' => 500 * 1024 * 1024,

    'download_ttl_minutes' => 60,

];
