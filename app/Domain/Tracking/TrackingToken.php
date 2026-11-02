<?php

namespace App\Domain\Tracking;

use Illuminate\Support\Str;

class TrackingToken
{
    public function issue(): string
    {
        return Str::upper(Str::random(16));
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
