<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * Horizon exposes queue internals, so access is limited to platform admins
     * (`PLATFORM_ADMIN_EMAILS`). Local development keeps the UI reachable.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            if (app()->environment('local')) {
                return true;
            }

            if ($user === null) {
                return false;
            }

            $admins = config('deployflow.platform_admins', []);

            return is_array($admins) && in_array($user->email, $admins, true);
        });
    }
}
