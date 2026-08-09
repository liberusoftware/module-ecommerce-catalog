<?php

namespace Liberu\Ecommerce\Catalog;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\Catalog\Models\Brand;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;
use Liberu\Ecommerce\Catalog\Models\Vendor;
use Liberu\Ecommerce\Catalog\Policies\ProductPolicy;
use Liberu\Ecommerce\Catalog\Policies\TaxonomyPolicy;
use Liberu\Ecommerce\Catalog\Telemetry\DomainEventLogger;

/**
 * Registered by `ModuleManagerServiceProvider` from `module.json`, never by
 * Composer discovery — the package ships no `extra.laravel.providers`, so an
 * install boots nothing until the deployment names the module in
 * `MODULES_ENABLED`.
 */
class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/catalog.php', 'catalog');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Registered here rather than left to Laravel's convention: the
        // convention maps `App\Models\X` to `App\Policies\XPolicy`, and this
        // module's models are in neither namespace.
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, TaxonomyPolicy::class);
        Gate::policy(ProductCollection::class, TaxonomyPolicy::class);
        Gate::policy(Brand::class, TaxonomyPolicy::class);
        Gate::policy(Vendor::class, TaxonomyPolicy::class);

        // Subscribed unconditionally, and silent unless the deployment turns
        // telemetry on. Gating the subscription on config instead would make
        // the setting un-changeable at runtime, which is exactly the thing a
        // deployment wants to flip while it is investigating something.
        Event::subscribe(DomainEventLogger::class);

        $this->publishes([
            __DIR__.'/../config/catalog.php' => config_path('catalog.php'),
        ], 'catalog-config');
    }
}
