<?php

namespace App\Providers;

use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\BusinessUnitPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(BusinessUnit::class, BusinessUnitPolicy::class);

        Paginator::useBootstrapFive();
    }
}
