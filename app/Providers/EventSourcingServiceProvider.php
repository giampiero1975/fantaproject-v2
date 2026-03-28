<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\EventSourcing\Facades\Projectionist;
use App\Projections\PlayerProjectionProjector;
use App\Projections\PlayerProjector;
use App\Projections\UserProjector;

class EventSourcingServiceProvider extends ServiceProvider
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
        Projectionist::addProjectors([
            PlayerProjectionProjector::class,
            PlayerProjector::class,
            UserProjector::class,
        ]);
    }
}
