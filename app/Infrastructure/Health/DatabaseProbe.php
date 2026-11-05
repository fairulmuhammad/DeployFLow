<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use Illuminate\Support\Facades\DB;

final class DatabaseProbe implements Probe
{
    public function name(): string
    {
        return 'database';
    }

    public function check(): bool
    {
        // getPdo() opens (or reuses) the connection and throws on failure.
        DB::connection()->getPdo();

        return true;
    }
}
