<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Health\DatabaseProbe;
use App\Infrastructure\Health\HealthChecker;
use App\Infrastructure\Health\Probe;
use App\Infrastructure\Health\RedisProbe;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag([
            DatabaseProbe::class,
            RedisProbe::class,
        ], 'deployflow.health_probes');

        $this->app->bind(HealthChecker::class, function (Application $app): HealthChecker {
            /** @var iterable<Probe> $probes */
            $probes = $app->tagged('deployflow.health_probes');

            return new HealthChecker($probes);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
