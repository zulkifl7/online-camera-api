<?php

namespace App\Services;

class TemporaryPasswordGenerator
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }
}
