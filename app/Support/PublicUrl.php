<?php

namespace App\Support;

class PublicUrl
{
    public static function to(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $forwarded = request()->headers->get('x-forwarded-host');

        if (is_string($forwarded) && $forwarded !== '') {
            $proto = request()->headers->get('x-forwarded-proto', 'http');
            $proto = trim(explode(',', (string) $proto)[0]);
            $host = trim(explode(',', $forwarded)[0]);

            return $proto.'://'.$host.$path;
        }

        return url($path);
    }
}
