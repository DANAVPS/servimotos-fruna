<?php

namespace App\Providers;

use App\Models\CategoriaRepuesto;
use App\Models\Cliente;
use App\Models\Distribuidor;
use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\TipoCliente;
use App\Observers\TallerObserver;
use App\Policies\CategoriaRepuestoPolicy;
use App\Policies\ClientePolicy;
use App\Policies\DistribuidorPolicy;
use App\Policies\OrdenServicioPolicy;
use App\Policies\RepuestoPolicy;
use App\Policies\TipoClientePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Repuesto::class, RepuestoPolicy::class);
        Gate::policy(Distribuidor::class, DistribuidorPolicy::class);
        Gate::policy(CategoriaRepuesto::class, CategoriaRepuestoPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(TipoCliente::class, TipoClientePolicy::class);

        Taller::observe(TallerObserver::class);
    }
}
