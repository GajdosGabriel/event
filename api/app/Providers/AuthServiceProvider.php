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

        // Globálne povolenie pre super-admina — ale iba mimo /dashboard.
        // V dashboarde (organizátorská časť appky) sa super-admin správa ako
        // ktorýkoľvek iný používateľ a podlieha rovnakým pravidlám vlastníctva
        // kanála aj archivovanému zámku (DeniesArchivedUpdate) — inak by v dashboarde
        // vždy videl "Upraviť" aj tam, kde by to bežný organizátor nemal.
        // V /admin (middleware role:super-admin) ostáva plný bypass, lebo admin
        // rozhranie musí vedieť spravovať čokoľvek naprieč všetkými kanálmi.
        Gate::before(function (User $user, string $ability) {
            if (! $user->hasRole('super-admin')) {
                return null;
            }

            if (request()->routeIs('dashboard.*')) {
                return null;
            }

            return true;
        });
    }
}
