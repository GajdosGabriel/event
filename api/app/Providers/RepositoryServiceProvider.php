<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            'App\Repositories\Contracts\UserRepository',
            'App\Repositories\Eloquent\EloquentUserRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\EventRepository',
            'App\Repositories\Eloquent\EloquentEventRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\CanalRepository',
            'App\Repositories\Eloquent\EloquentCanalRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\FileRepository',
            'App\Repositories\Eloquent\EloquentFileRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\MunicipalityRepository',
            'App\Repositories\Eloquent\EloquentMunicipalityRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\OrganizationRepository',
            'App\Repositories\Eloquent\EloquentOrganizationRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\VenueRepository',
            'App\Repositories\Eloquent\EloquentVenueRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\TicketRepository',
            'App\Repositories\Eloquent\EloquentTicketRepository'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
