<?php

namespace App\Providers;

use App\Models\Organization;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);

        // Globálne povolenie pre super-admina
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }
        });
    }
}
