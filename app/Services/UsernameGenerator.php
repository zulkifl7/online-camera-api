<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Str;

class UsernameGenerator
{
    public function generate(string $displayName, ?string $ignoreClientId = null): string
    {
        $base = Str::of($displayName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        $base = trim((string) preg_replace('/-+/', '-', $base), '-');

        if ($base === '' || strlen($base) < 2) {
            $base = 'client';
        }

        $base = trim(Str::limit($base, 40, ''), '-');

        if ($base === '') {
            $base = 'client';
        }

        $username = $base;
        $suffix = 2;

        while ($this->taken($username, $ignoreClientId)) {
            $username = $base.'-'.$suffix;
            $suffix++;
        }

        return $username;
    }

    private function taken(string $username, ?string $ignoreClientId): bool
    {
        return Client::query()
            ->where('username', $username)
            ->when($ignoreClientId, fn ($query) => $query->where('id', '!=', $ignoreClientId))
            ->exists();
    }
}
