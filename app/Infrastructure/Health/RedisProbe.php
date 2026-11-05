<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use Illuminate\Support\Facades\Redis;

final class RedisProbe implements Probe
{
    public function name(): string
    {
        return 'redis';
    }

    public function check(): bool
    {
        return (bool) Redis::connection()->ping();
    }
}
