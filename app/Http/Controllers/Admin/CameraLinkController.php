<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CameraLinkResource;
use App\Models\CameraLink;
use App\Services\CameraLinkService;

class CameraLinkController extends Controller
{
    public function __construct(private readonly CameraLinkService $links) {}

    public function rotate(CameraLink $link)
    {
        $replacement = $this->links->rotate(request()->user('admin'), $link);
        $replacement->load('collection');

        return (new CameraLinkResource($replacement))
            ->additional([
                'meta' => [
                    'qr_label' => $replacement->collection?->qrLabel(),
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }
}
