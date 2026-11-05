<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

interface Probe
{
    /**
     * Stable identifier used as the key in the health response.
     */
    public function name(): string;

    /**
     * Return true when the dependency is reachable and answering.
     */
    public function check(): bool;
}
