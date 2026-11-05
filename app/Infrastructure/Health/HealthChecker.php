<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use Throwable;

final class HealthChecker
{
    /** @var list<Probe> */
    private array $probes = [];

    /**
     * @param  iterable<Probe>  $probes
     */
    public function __construct(iterable $probes)
    {
        foreach ($probes as $probe) {
            $this->probes[] = $probe;
        }
    }

    /**
     * Run every probe and reduce the results to a single report.
     *
     * Exceptions are reduced to a failed check: a health endpoint must never
     * leak driver messages, connection strings, or credentials.
     *
     * @return array{status: 'ok'|'degraded', checks: array<string, bool>}
     */
    public function run(): array
    {
        $checks = [];

        foreach ($this->probes as $probe) {
            $checks[$probe->name()] = $this->probe($probe);
        }

        $healthy = $checks !== [] && ! in_array(false, $checks, true);

        return [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ];
    }

    private function probe(Probe $probe): bool
    {
        try {
            return $probe->check();
        } catch (Throwable) {
            return false;
        }
    }
}
