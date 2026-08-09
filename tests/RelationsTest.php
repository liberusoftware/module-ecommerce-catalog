<?php

declare(strict_types=1);

use Liberu\Ecommerce\Catalog\Actions\AddProductToCollection;
use Liberu\Ecommerce\Catalog\Actions\AddVariant;
use Liberu\Ecommerce\Catalog\Actions\CreateBrand;
use Liberu\Ecommerce\Catalog\Actions\CreateCollection;
use Liberu\Ecommerce\Catalog\Actions\CreateVendor;
use Liberu\Ecommerce\Catalog\Actions\PublishToChannel;
use Liberu\Ecommerce\Catalog\Actions\SetProductOption;
use Liberu\Ecommerce\Catalog\Actions\SyncProductTags;
use Liberu\Ecommerce\Catalog\Models\Brand;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;
use Liberu\Ecommerce\Catalog\Models\ProductOption;
use Liberu\Ecommerce\Catalog\Models\ProductVariant;
use Liberu\Ecommerce\Catalog\Models\Tag;
use Liberu\Ecommerce\Catalog\Models\Vendor;

/*
 * The inverse relations, which nothing else in the suite walks.
 *
 * They are here because an inverse that nobody calls is exactly the kind of
 * thing that stays broken silently — a wrong foreign key on a `hasMany` is
 * invisible until the one panel that lists from that side is opened.
 */

it('walks from a product down to everything it owns', function () {
    $product = Product::factory()->create();
    (new AddVariant())->handle($product, 'TEE-S');
    (new SetProductOption())->handle($product, 'Size', ['S']);
    (new SyncProductTags())->handle($product, ['Wool']);
    (new PublishToChannel())->handle($product, 1);
    $collection = (new CreateCollection())->handle('Summer');
    (new AddProductToCollection())->handle($collection, $product);

    expect($product->variants()->count())->toBe(1)
        ->and($product->options()->count())->toBe(1)
        ->and($product->tags()->count())->toBe(1)
        ->and($product->publications()->count())->toBe(1)
        ->and($product->collections()->count())->toBe(1)
        ->and($product->collections()->first()->id)->toBe($collection->id);
});

it('walks back up from every child to the product it belongs to', function () {
    $product = Product::factory()->create();
    $variant = (new AddVariant())->handle($product, 'TEE-S');
    $option = (new SetProductOption())->handle($product, 'Size', ['S']);
    $publication = (new PublishToChannel())->handle($product, 1);

    expect($variant->product->id)->toBe($product->id)
        ->and($option->product->id)->toBe($product->id)
        ->and($publication->product->id)->toBe($product->id)
        ->and(ProductVariant::query()->first()->product->slug)->toBe($product->slug)
        ->and(ProductOption::query()->first()->product->slug)->toBe($product->slug);
});

it('lists a product from the things it is filed under', function () {
    $category = Category::factory()->create();
    $brand = (new CreateBrand())->handle('Acme');
    $vendor = (new CreateVendor())->handle('Supplier');
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'vendor_id' => $vendor->id,
    ]);
    (new SyncProductTags())->handle($product, ['Wool']);

    expect($category->products()->count())->toBe(1)
        ->and($brand->products()->count())->toBe(1)
        ->and($vendor->products()->count())->toBe(1)
        ->and(Tag::query()->first()->products()->count())->toBe(1)
        ->and($product->category->id)->toBe($category->id)
        ->and($product->brand->id)->toBe($brand->id)
        ->and($product->vendor->id)->toBe($vendor->id);
});

it('walks a category up as well as down', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->under($parent)->create();

    expect($child->parent->id)->toBe($parent->id)
        ->and($parent->children()->count())->toBe(1)
        ->and($parent->parent)->toBeNull();
});

it('resolves every addressable model by its slug in a route', function (string $model) {
    expect((new $model())->getRouteKeyName())->toBe('slug');
})->with([
    'products' => [Product::class],
    'categories' => [Category::class],
    'collections' => [ProductCollection::class],
    'tags' => [Tag::class],
    'brands' => [Brand::class],
    'vendors' => [Vendor::class],
]);

it('drops an empty option value rather than rendering it as a blank segment', function () {
    // A caller joining all three axes blindly renders "Red / Large / ".
    $variant = ProductVariant::factory()->create(['option1' => 'Red', 'option2' => '', 'option3' => null]);

    expect($variant->optionValues())->toBe(['Red']);
});

it('ships a working factory for every model, because a consumer builds fixtures with them', function (string $model) {
    // The factories are public surface: a presentation package's tests build
    // their fixtures with these, and a broken one is discovered in somebody
    // else's repository rather than this one.
    expect($model::factory()->create())->toBeInstanceOf($model);
})->with([
    'products' => [Product::class],
    'variants' => [ProductVariant::class],
    'options' => [ProductOption::class],
    'categories' => [Category::class],
    'collections' => [ProductCollection::class],
    'tags' => [Tag::class],
    'brands' => [Brand::class],
    'vendors' => [Vendor::class],
]);
