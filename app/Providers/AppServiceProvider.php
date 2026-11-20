<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;
use App\Domain\Organizations\Policies\OrganizationPolicy;
use App\Http\RouteBinding\OrganizationRouteBinding;
use App\Infrastructure\Audit\AuditRecorder;
use App\Infrastructure\Health\DatabaseProbe;
use App\Infrastructure\Health\HealthChecker;
use App\Infrastructure\Health\Probe;
use App\Infrastructure\Health\RedisProbe;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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

        $this->app->singleton(AuditRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePasswords();
        $this->configureRateLimiting();
        $this->registerPolicies();
        $this->bindRouteParameters();
    }

    private function registerPolicies(): void
    {
        /** @var GateContract $gate */
        $gate = $this->app->make(GateContract::class);

        $gate->policy(Organization::class, OrganizationPolicy::class);
        $gate->policy(OrganizationInvitation::class, OrganizationPolicy::class);
    }

    private function bindRouteParameters(): void
    {
        (new OrganizationRouteBinding)->register();
    }

    /**
     * Minimum 12 characters. The breach check is network-bound, so it runs in
     * production only; local and test runs stay offline and deterministic.
     */
    private function configurePasswords(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12);

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Limits from docs/API.md §6.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(6)
            ->by((string) $request->ip()));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->credentialKey($request)));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(3)
            ->by($this->credentialKey($request)));

        RateLimiter::for('verification-send', fn (Request $request): Limit => Limit::perMinute(1)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    private function credentialKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        );
    }
}
