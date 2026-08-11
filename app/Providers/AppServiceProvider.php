<?php

namespace App\Providers;

use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\BusinessUnitPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\UserPolicy;
use App\Services\AiDocumentExtractionService;
use App\Services\PdfExtractionService;
use App\Services\PdfOcrService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Smalot\PdfParser\Parser;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfExtractionService::class, function ($app): PdfExtractionService {
            return new PdfExtractionService(
                $app->make(Parser::class),
                $app->make(PdfOcrService::class),
                $app->make(AiDocumentExtractionService::class),
            );
        });
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
