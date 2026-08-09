<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * The migration is the module's public surface as much as its classes are — a
 * consumer's data lives in these tables, and a column quietly renamed or
 * dropped between releases is an outage on deploy rather than a failing build.
 *
 * These assert the shape a consumer may rely on. Changing one of them on
 * purpose means an entry in the changelog and, past 1.0.0, a major version.
 */
it('creates every table the module owns', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'products',
    'product_categories',
    'product_variants',
    'product_options',
    'collections',
    'collection_items',
    'tags',
    'product_tag',
    'ecommerce_catalog_brands',
    'ecommerce_catalog_vendors',
    'ecommerce_catalog_publications',
]);

it('keeps the extracted tables on their bare names and prefixes the invented ones', function () {
    // MODULE_DEVELOPMENT.md §1.5: a table that existed in the host before this
    // package did keeps its name when it moves; a table this package invents
    // carries the module prefix. Both halves are asserted because getting
    // either one wrong is silent until a second module claims the name.
    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasTable('ecommerce_catalog_products'))->toBeFalse()
        ->and(Schema::hasTable('product_categories'))->toBeTrue()
        ->and(Schema::hasTable('ecommerce_catalog_categories'))->toBeFalse()
        ->and(Schema::hasTable('brands'))->toBeFalse()
        ->and(Schema::hasTable('ecommerce_catalog_brands'))->toBeTrue()
        ->and(Schema::hasTable('vendors'))->toBeFalse()
        ->and(Schema::hasTable('ecommerce_catalog_vendors'))->toBeTrue()
        ->and(Schema::hasTable('publications'))->toBeFalse()
        ->and(Schema::hasTable('ecommerce_catalog_publications'))->toBeTrue();
});

it('gives products the columns a consumer reads', function (string $column) {
    expect(Schema::hasColumn('products', $column))->toBeTrue();
})->with([
    'id', 'team_id', 'store_id', 'category_id', 'brand_id', 'vendor_id',
    'name', 'slug', 'description', 'short_description', 'long_description',
    'featured_image', 'meta_title', 'meta_description', 'meta_keywords',
    'status', 'visibility', 'available_from', 'available_until',
    'is_featured', 'position', 'deleted_at', 'created_at', 'updated_at',
]);

it('keeps price and stock out of the catalogue entirely', function (string $column) {
    // Pricing and Inventory Ledger extend a product through their own tables
    // keyed on `products.id`. A column here would make this package the owner
    // of a rule it does not enforce, and two owners of one number is how they
    // disagree.
    expect(Schema::hasColumn('products', $column))->toBeFalse()
        ->and(Schema::hasColumn('product_variants', $column))->toBeFalse();
})->with(['price', 'compare_at_price', 'cost', 'inventory_count', 'inventory_quantity', 'stock']);

it('gives variants and publications the columns a consumer reads', function (string $table, array $columns) {
    foreach ($columns as $column) {
        expect(Schema::hasColumn($table, $column))->toBeTrue();
    }
})->with([
    'variants' => ['product_variants', ['id', 'product_id', 'sku', 'title', 'option1', 'option2', 'option3', 'barcode', 'weight', 'weight_unit', 'requires_shipping', 'position']],
    'options' => ['product_options', ['id', 'product_id', 'name', 'position', 'values']],
    'publications' => ['ecommerce_catalog_publications', ['id', 'product_id', 'channel_id', 'published_at', 'unpublished_at']],
    'categories' => ['product_categories', ['id', 'team_id', 'parent_category_id', 'name', 'slug', 'position']],
    'collections' => ['collections', ['id', 'team_id', 'name', 'slug', 'position', 'deleted_at']],
    'brands' => ['ecommerce_catalog_brands', ['id', 'team_id', 'name', 'slug', 'logo', 'website']],
    'vendors' => ['ecommerce_catalog_vendors', ['id', 'team_id', 'name', 'slug', 'contact_email']],
]);

it('starts a product not for sale and not visible, whatever the caller forgot to say', function () {
    // Inserted through the query builder rather than the model on purpose: this
    // asserts the database's own defaults, which is what protects a row written
    // by a seeder, a fixture or another module's migration.
    $id = DB::table('products')->insertGetId([
        'name' => 'Bare', 'slug' => 'bare', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $product = Product::query()->find($id);

    expect($product->status->value)->toBe('draft')
        ->and($product->visibility->value)->toBe('hidden')
        ->and($product->is_featured)->toBeFalse()
        ->and($product->isAvailableOn())->toBeFalse();
});

it('refuses two rows claiming one slug, at the database and not only in the action', function (string $table, array $row) {
    DB::table($table)->insert($row);
    DB::table($table)->insert($row);
})->with([
    'products' => ['products', ['name' => 'A', 'slug' => 'a', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']],
    'categories' => ['product_categories', ['name' => 'A', 'slug' => 'a', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']],
    'collections' => ['collections', ['name' => 'A', 'slug' => 'a', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']],
    'tags' => ['tags', ['name' => 'A', 'slug' => 'a', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']],
    'brands' => ['ecommerce_catalog_brands', ['name' => 'A', 'slug' => 'a', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']],
    'vendors' => ['ecommerce_catalog_vendors', ['name' => 'A', 'slug' => 'a', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']],
])->throws(QueryException::class);

it('refuses two variants claiming one SKU, across products and not merely within one', function () {
    // A SKU is what a warehouse, a supplier feed and a marketplace listing all
    // key on. Two products sharing one is a mis-ship, so the last defence is in
    // the schema rather than in the action alone.
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    DB::table('product_variants')->insert(['product_id' => $first->id, 'sku' => 'TEE-S', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('product_variants')->insert(['product_id' => $second->id, 'sku' => 'TEE-S', 'created_at' => now(), 'updated_at' => now()]);
})->throws(QueryException::class);

it('lets any number of variants carry no SKU at all', function () {
    $product = Product::factory()->create();
    $row = ['product_id' => $product->id, 'sku' => null, 'created_at' => now(), 'updated_at' => now()];

    DB::table('product_variants')->insert($row);
    DB::table('product_variants')->insert($row);

    expect(DB::table('product_variants')->count())->toBe(2);
});

it('refuses a duplicate pairing, wherever one would be meaningless', function (string $table, callable $row) {
    DB::table($table)->insert($row());
    DB::table($table)->insert($row());
})->with([
    // One level of closure, not two: Pest hands a closure inside a dataset row
    // through untouched rather than calling it, so a closure returning a
    // closure would assert on a row that was never built.
    'a product tagged twice' => ['product_tag', fn (): array => [
        'product_id' => 1, 'tag_id' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]],
    'a product in one collection twice' => ['collection_items', fn (): array => [
        'collection_id' => 1, 'product_id' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]],
    'a product published to one channel twice' => ['ecommerce_catalog_publications', fn (): array => [
        'product_id' => 1, 'channel_id' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]],
    'a product with two options of one name' => ['product_options', fn (): array => [
        'product_id' => 1, 'name' => 'Size', 'values' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ]],
])->throws(QueryException::class);

it('declares what happens to a child when its parent goes', function (string $table, string $column, string $parent, string $onDelete) {
    // The declaration is asserted rather than the deletion. Whether the engine
    // acts on it is a connection setting — SQLite enforces foreign keys only
    // with the pragma on, and a pragma inside RefreshDatabase's transaction is
    // a no-op — so a behavioural test here would pass or fail on how the suite
    // is wired rather than on what this migration says.
    $foreignKey = collect(Schema::getForeignKeys($table))
        ->first(fn (array $key): bool => in_array($column, $key['columns'], true));

    expect($foreignKey)->not->toBeNull()
        ->and($foreignKey['foreign_table'])->toBe($parent)
        ->and(strtolower((string) $foreignKey['on_delete']))->toBe($onDelete);
})->with([
    // Owned outright: a variant, an option or a publication has no meaning
    // without its product.
    'variants' => ['product_variants', 'product_id', 'products', 'cascade'],
    'options' => ['product_options', 'product_id', 'products', 'cascade'],
    'publications' => ['ecommerce_catalog_publications', 'product_id', 'products', 'cascade'],
    'tag pivot' => ['product_tag', 'product_id', 'products', 'cascade'],
    'collection membership' => ['collection_items', 'collection_id', 'collections', 'cascade'],
    'subcategories' => ['product_categories', 'parent_category_id', 'product_categories', 'cascade'],
    // Merely filed under: a category, brand or vendor is how a product is
    // described, and losing the description must not lose the product.
    'category' => ['products', 'category_id', 'product_categories', 'set null'],
    'brand' => ['products', 'brand_id', 'ecommerce_catalog_brands', 'set null'],
    'vendor' => ['products', 'vendor_id', 'ecommerce_catalog_vendors', 'set null'],
]);

it('leaves the tenant and channel columns unconstrained, because those tables belong to somebody else', function (string $table, string $column) {
    // `teams` belongs to the host application and `channels` to Commerce Core,
    // which is not a dependency here. A package that constrains a table it does
    // not own cannot be installed without it.
    $constrained = collect(Schema::getForeignKeys($table))
        ->contains(fn (array $key): bool => in_array($column, $key['columns'], true));

    expect($constrained)->toBeFalse()
        ->and(Schema::hasColumn($table, $column))->toBeTrue();
})->with([
    'product team' => ['products', 'team_id'],
    'product store' => ['products', 'store_id'],
    'publication channel' => ['ecommerce_catalog_publications', 'channel_id'],
    'category team' => ['product_categories', 'team_id'],
    'brand team' => ['ecommerce_catalog_brands', 'team_id'],
]);
