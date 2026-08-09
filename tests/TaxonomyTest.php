<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Catalog\Actions\AddProductToCollection;
use Liberu\Ecommerce\Catalog\Actions\CreateBrand;
use Liberu\Ecommerce\Catalog\Actions\CreateCategory;
use Liberu\Ecommerce\Catalog\Actions\CreateCollection;
use Liberu\Ecommerce\Catalog\Actions\CreateVendor;
use Liberu\Ecommerce\Catalog\Actions\MoveCategory;
use Liberu\Ecommerce\Catalog\Actions\RemoveProductFromCollection;
use Liberu\Ecommerce\Catalog\Actions\SyncProductTags;
use Liberu\Ecommerce\Catalog\Events\CategoryMoved;
use Liberu\Ecommerce\Catalog\Events\ProductAddedToCollection;
use Liberu\Ecommerce\Catalog\Events\ProductRemovedFromCollection;
use Liberu\Ecommerce\Catalog\Events\ProductTagsChanged;
use Liberu\Ecommerce\Catalog\Exceptions\CategoryCycle;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;
use Liberu\Ecommerce\Catalog\Models\Tag;

it('slugs a category uniquely across the whole tree, not within a parent', function () {
    // Two "Accessories" under different parents resolving to one route is the
    // failure this prevents — a category's URL is its slug.
    $men = (new CreateCategory())->handle('Men');
    $women = (new CreateCategory())->handle('Women');

    $first = (new CreateCategory())->handle('Accessories', $men->id);
    $second = (new CreateCategory())->handle('Accessories', $women->id);

    expect($first->slug)->toBe('accessories')
        ->and($second->slug)->toBe('accessories-2');
});

it('appends siblings within their own parent', function () {
    $root = (new CreateCategory())->handle('Men');

    $shirts = (new CreateCategory())->handle('Shirts', $root->id);
    $coats = (new CreateCategory())->handle('Coats', $root->id);

    expect([$shirts->position, $coats->position])->toBe([1, 2]);
});

it('walks a subtree including itself', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->under($root)->create();
    $grandchild = Category::factory()->under($child)->create();
    Category::factory()->create();

    expect($root->descendantIds())->toBe([$root->id, $child->id, $grandchild->id])
        ->and($grandchild->descendantIds())->toBe([$grandchild->id]);
});

it('refuses to move a category under itself or its own descendant', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->under($root)->create();
    $grandchild = Category::factory()->under($child)->create();

    // Not a wrong answer but a request that never returns: every breadcrumb and
    // descendant query walks parents until it hits null, and a ring has none.
    expect(fn () => (new MoveCategory())->handle($root, $root->id))->toThrow(CategoryCycle::class)
        ->and(fn () => (new MoveCategory())->handle($root, $grandchild->id))->toThrow(CategoryCycle::class);
});

it('moves a category and promotes one to a root', function () {
    $men = Category::factory()->create();
    $women = Category::factory()->create();
    $shirts = Category::factory()->under($men)->create();

    (new MoveCategory())->handle($shirts, $women->id);
    expect($shirts->fresh()->parent_category_id)->toBe($women->id);

    (new MoveCategory())->handle($shirts, null);
    expect($shirts->fresh()->parent_category_id)->toBeNull()
        ->and(Category::query()->roots()->pluck('id')->all())->toContain($shirts->id);
});

it('says nothing when a category was already where it was asked to go', function () {
    Event::fake([CategoryMoved::class]);
    $root = Category::factory()->create();

    (new MoveCategory())->handle($root, null);

    Event::assertNotDispatched(CategoryMoved::class);
});

it('carries the previous parent, because a cache keyed on the old path has no other way to find itself', function () {
    Event::fake([CategoryMoved::class]);
    $men = Category::factory()->create();
    $women = Category::factory()->create();
    $shirts = Category::factory()->under($men)->create();

    (new MoveCategory())->handle($shirts, $women->id);

    Event::assertDispatched(
        CategoryMoved::class,
        fn (CategoryMoved $event): bool => $event->fromParentId === $men->id && $event->toParentId === $women->id,
    );
});

it('folds tags that differ only in case or spacing into one', function () {
    $product = Product::factory()->create();

    (new SyncProductTags())->handle($product, ['Water Resistant', 'water resistant', 'WATER RESISTANT']);

    expect(Tag::query()->count())->toBe(1)
        ->and($product->tags()->count())->toBe(1)
        ->and(Tag::query()->first()->slug)->toBe('water-resistant');
});

it('ignores blank tag names instead of creating a tag called nothing', function () {
    $product = Product::factory()->create();

    (new SyncProductTags())->handle($product, ['  ', '', 'Wool']);

    expect(Tag::query()->pluck('slug')->all())->toBe(['wool']);
});

it('reports what moved rather than what the set now is', function () {
    Event::fake([ProductTagsChanged::class]);
    $product = Product::factory()->create();

    (new SyncProductTags())->handle($product, ['Wool', 'Warm']);
    (new SyncProductTags())->handle($product, ['Wool', 'Waterproof']);

    Event::assertDispatched(
        ProductTagsChanged::class,
        fn (ProductTagsChanged $event): bool => $event->attached === ['waterproof'] && $event->detached === ['warm'],
    );
});

it('stays silent when a bulk edit re-saves the same tags', function () {
    $product = Product::factory()->create();
    (new SyncProductTags())->handle($product, ['Wool']);

    Event::fake([ProductTagsChanged::class]);
    (new SyncProductTags())->handle($product, ['Wool']);

    Event::assertNotDispatched(ProductTagsChanged::class);
});

it('clears every tag when given nothing', function () {
    $product = Product::factory()->create();
    (new SyncProductTags())->handle($product, ['Wool']);

    (new SyncProductTags())->handle($product, []);

    expect($product->tags()->count())->toBe(0);
});

it('keeps a collection in merchandised order and appends to the end', function () {
    $collection = (new CreateCollection())->handle('Summer');
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    (new AddProductToCollection())->handle($collection, $first);
    (new AddProductToCollection())->handle($collection, $second);

    expect($collection->products()->pluck('products.id')->all())->toBe([$first->id, $second->id])
        ->and($collection->products()->get()->pluck('pivot.position')->all())->toBe([1, 2]);
});

it('respects a position the merchant chose', function () {
    $collection = (new CreateCollection())->handle('Summer');
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    (new AddProductToCollection())->handle($collection, $first, 20);
    (new AddProductToCollection())->handle($collection, $second, 10);

    expect($collection->products()->pluck('products.id')->all())->toBe([$second->id, $first->id]);
});

it('treats adding something already in the collection as a no-op, not an integrity error', function () {
    Event::fake([ProductAddedToCollection::class]);
    $collection = (new CreateCollection())->handle('Summer');
    $product = Product::factory()->create();

    (new AddProductToCollection())->handle($collection, $product);
    (new AddProductToCollection())->handle($collection, $product);

    expect($collection->products()->count())->toBe(1);
    Event::assertDispatchedTimes(ProductAddedToCollection::class, 1);
});

it('is silent removing a product that was never in the collection', function () {
    Event::fake([ProductRemovedFromCollection::class]);
    $collection = (new CreateCollection())->handle('Summer');

    (new RemoveProductFromCollection())->handle($collection, Product::factory()->create());

    Event::assertNotDispatched(ProductRemovedFromCollection::class);
});

it('removes a product from a collection without touching the product', function () {
    $collection = (new CreateCollection())->handle('Summer');
    $product = Product::factory()->create();
    (new AddProductToCollection())->handle($collection, $product);

    (new RemoveProductFromCollection())->handle($collection, $product);

    expect($collection->products()->count())->toBe(0)
        ->and(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('creates brands and vendors with unique slugs and the team that owns them', function () {
    $brand = (new CreateBrand())->handle('Acme', 7, ['website' => 'https://acme.test']);
    $duplicate = (new CreateBrand())->handle('Acme', 7);
    $vendor = (new CreateVendor())->handle('Acme', 8, ['contact_email' => 'buyer@acme.test']);

    expect($brand->slug)->toBe('acme')
        ->and($duplicate->slug)->toBe('acme-2')
        ->and($brand->website)->toBe('https://acme.test')
        ->and($brand->team_id)->toBe(7)
        // Brands and vendors are separate namespaces: a maker and a supplier
        // with the same name are two rows, and neither renames the other.
        ->and($vendor->slug)->toBe('acme')
        ->and($vendor->contact_email)->toBe('buyer@acme.test');
});

it('soft deletes a collection so its slug and membership survive a mistake', function () {
    $collection = (new CreateCollection())->handle('Summer');

    $collection->delete();

    expect(ProductCollection::query()->count())->toBe(0)
        ->and(ProductCollection::query()->withTrashed()->count())->toBe(1);
});
