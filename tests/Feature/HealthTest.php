<?php

declare(strict_types=1);

use App\Infrastructure\Health\HealthChecker;
use App\Infrastructure\Health\Probe;

function fakeProbe(string $name, bool $ok): Probe
{
    return new class($name, $ok) implements Probe
    {
        public function __construct(
            private readonly string $name,
            private readonly bool $ok,
        ) {}

        public function name(): string
        {
            return $this->name;
        }

        public function check(): bool
        {
            return $this->ok;
        }
    };
}

function throwingProbe(string $name): Probe
{
    return new class($name) implements Probe
    {
        public function __construct(private readonly string $name) {}

        public function name(): string
        {
            return $this->name;
        }

        public function check(): bool
        {
            throw new RuntimeException('connection refused: postgres://user:secret@db:5432/deployflow');
        }
    };
}

it('reports healthy when every dependency answers', function () {
    $this->app->instance(HealthChecker::class, new HealthChecker([
        fakeProbe('database', true),
        fakeProbe('redis', true),
    ]));

    $this->get('/healthz')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson([
            'status' => 'ok',
            'checks' => ['database' => true, 'redis' => true],
        ]);
});

it('reports degraded when a dependency is down', function () {
    $this->app->instance(HealthChecker::class, new HealthChecker([
        fakeProbe('database', true),
        fakeProbe('redis', false),
    ]));

    $this->get('/healthz')
        ->assertStatus(503)
        ->assertJson([
            'status' => 'degraded',
            'checks' => ['database' => true, 'redis' => false],
        ]);
});

it('never leaks a dependency exception message', function () {
    $this->app->instance(HealthChecker::class, new HealthChecker([
        throwingProbe('database'),
    ]));

    $response = $this->get('/healthz')->assertStatus(503);

    expect($response->getContent())
        ->not->toContain('secret')
        ->not->toContain('postgres://');
});
