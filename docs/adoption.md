# Adopting `liberusoftware/ecommerce-catalog`

## Install

```bash
composer require liberusoftware/ecommerce-catalog
```

Installing boots nothing. The package ships no `extra.laravel.providers`, so
`ModuleManagerServiceProvider` is the only thing that registers it, and only
when the deployment names it:

```dotenv
MODULES_ENABLED=ecommerce-catalog
```

Enabling it loads the migration and registers the policies and the (silent)
telemetry subscriber. Nothing else changes.

## Adopting into an application that already has a catalogue

This is the case the Ecommerce host is in, and it is deliberately undramatic.

**The migration is a no-op on your database.** Every `Schema::create` is guarded
by `hasTable`, and the tables this module owns are the ones you already have:
`products`, `product_categories`, `product_variants`, `product_options`,
`collections`, `collection_items`, `tags`, `product_tag`. It will create three
tables you do not have — `ecommerce_catalog_brands`, `ecommerce_catalog_vendors`
and `ecommerce_catalog_publications`.

**Four columns your `products` table is missing.** The module reads `status`,
`visibility`, `available_from` and `available_until`, and a fresh install gets
them from this migration. A host adopting the module adds them itself, because
the guarded `create` will not:

```php
Schema::table('products', function (Blueprint $table) {
    $table->string('status')->default('draft')->index();
    $table->string('visibility')->default('hidden')->index();
    $table->timestamp('available_from')->nullable();
    $table->timestamp('available_until')->nullable();
    $table->foreignId('brand_id')->nullable()->constrained('ecommerce_catalog_brands')->nullOnDelete();
    $table->foreignId('vendor_id')->nullable()->constrained('ecommerce_catalog_vendors')->nullOnDelete();
});
```

Then backfill: existing rows are live products, so they want
`status = 'active'` and `visibility = 'public'`, not the schema defaults, which
are chosen for rows that are being created rather than rows that already sell.
Getting this backwards hides the whole catalogue.

**`collections.slug` and `tags.slug`.** The host's `collections.slug` is
nullable; this module treats it as present and unique. `tags` has no slug at
all. Add and backfill both before enabling, or `SyncProductTags` will fail on
the first duplicate.

**Your `products` table keeps its extra columns.** `price`, `inventory_count`,
`pricing_type` and the rest are not in this module's `$fillable` and it will
never write them. They stay yours until Pricing and Inventory Ledger take them,
which is a separate move.

## What the host must supply

**The team model.** Every owned model resolves
`config('catalog.team_model')` at call time, defaulting to `App\Models\Team`. An
application whose team model lives elsewhere publishes the config and says so:

```bash
php artisan vendor:publish --tag=catalog-config
```

**Store scoping.** `products.store_id` is a plain indexed column and this module
never populates it. If your application scopes reads to a resolved store — the
Ecommerce host does, with a global scope — keep doing that; `scopeForStore()` is
here for callers that want the filter explicitly, and it guards the null case so
that "no store" narrows to nothing rather than to the unassigned rows.

**Channels, if you have them.** Publication is a `channel_id` this module stores
and never resolves. Every rule works on the number alone. If you want a panel to
show a channel's name, name the class:

```dotenv
CATALOG_CHANNEL_MODEL="Liberu\\Ecommerce\\CommerceCore\\Models\\Channel"
```

That makes `ProductPublication::channel()` loadable. Leave it unset and the
relation is simply never used — it throws if you call it, rather than guessing.

**Presentation.** This package has no Filament, Livewire or HTTP surface, by
rule. Those are one-to-one presentation packages
(`module-ecommerce-catalog-filament`, `-livewire`, `-api`) that delegate to the
actions, queries and policies here.

## Replacing host code

The host's `App\Models\Product` and friends become this module's models. The
mechanical part is the namespace; the parts worth reading twice:

- **Writes go through actions.** `$product->update(['status' => 'active'])`
  bypasses the transition table and the event. Use `ChangeProductStatus`.
- **`Product` here has no `price`, `displayPrice()`, `adjustInventory()` or
  `inventory_count`.** Those move to Pricing and Inventory Ledger. Until they
  land, the host keeps its own accessors against the columns it still owns.
- **Slug generation moved out of a model hook and into the create actions**, so
  it can be unique-by-suffix rather than colliding.
- **Tags are synced by name.** `SyncProductTags` replaces resolving tag ids at
  each call site.

## Configuration

| Key | Env | Default |
| --- | --- | --- |
| `catalog.team_model` | `CATALOG_TEAM_MODEL` | `App\Models\Team` |
| `catalog.channel_model` | `CATALOG_CHANNEL_MODEL` | `null` |
| `catalog.telemetry.enabled` | `CATALOG_TELEMETRY` | `false` |
| `catalog.telemetry.channel` | `CATALOG_TELEMETRY_CHANNEL` | `null` |

## Verifying the adoption

```bash
php artisan migrate
php artisan tinker
>>> Liberu\Ecommerce\Catalog\Models\Product::query()->availableOn()->count();
```

If that returns zero on a catalogue you know is live, the backfill above did not
run.
