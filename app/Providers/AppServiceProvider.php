<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\OrdenServicio;
use App\Policies\OrdenServicioPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
{
    $this->app->singleton(\App\Support\TenantManager::class);
}

    public function boot(): void
    {
        Gate::policy(OrdenServicio::class, OrdenServicioPolicy::class);
    }
}
