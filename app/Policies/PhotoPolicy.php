<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Photo;
use Illuminate\Contracts\Auth\Authenticatable;

class PhotoPolicy
{
    public function download(Authenticatable $user, Photo $photo): bool
    {
        return $user instanceof Client
            && $photo->client_id === $user->id
            && $photo->client_hidden_at === null
            && $photo->permanently_deleted_at === null
            && $photo->upload_status->value === 'ready';
    }

    public function hide(Authenticatable $user, Photo $photo): bool
    {
        return $user instanceof Client
            && $photo->client_id === $user->id
            && $photo->permanently_deleted_at === null;
    }
}
