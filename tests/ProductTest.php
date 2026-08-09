<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Catalog\Actions\CreateProduct;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Enums\Visibility;
use Liberu\Ecommerce\Catalog\Events\ProductCreated;
use Liberu\Ecommerce\Catalog\Models\Product;

it('creates a product that is not yet for sale and not yet visible', function () {
    $product = (new CreateProduct())->handle('Merino Crew');

    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->visibility)->toBe(Visibility::Hidden)
        ->and($product->slug)->toBe('merino-crew');
});

it('derives a unique slug by suffix rather than rejecting the second one', function () {
    $first = (new CreateProduct())->handle('Merino Crew');
    $second = (new CreateProduct())->handle('Merino Crew');
    $third = (new CreateProduct())->handle('Merino Crew');

    expect([$first->slug, $second->slug, $third->slug])->toBe(['merino-crew', 'merino-crew-2', 'merino-crew-3']);
});

it('falls back to a usable slug when the name slugs to nothing', function () {
    expect((new CreateProduct())->handle('・・・')->slug)->toBe('product');
});

it('counts a soft-deleted product as still holding its slug', function () {
    (new CreateProduct())->handle('Merino Crew')->delete();

    expect((new CreateProduct())->handle('Merino Crew')->slug)->toBe('merino-crew-2');
});

it('stamps the tenancy the caller passed and nothing the caller did not', function () {
    $product = (new CreateProduct())->handle('Merino Crew', teamId: 7, storeId: 3);

    expect($product->team_id)->toBe(7)
        ->and($product->store_id)->toBe(3);
});

it('refuses to let a payload smuggle a team in through the attributes', function () {
    // `team_id` and `store_id` are named arguments precisely so a spread of
    // request input cannot decide who owns the row.
    $product = (new CreateProduct())->handle('Merino Crew', teamId: 7, attributes: ['team_id' => 9, 'store_id' => 9]);

    expect($product->team_id)->toBe(7)
        ->and($product->store_id)->toBeNull();
});

it('takes the descriptive fields it was given', function () {
    $product = (new CreateProduct())->handle('Merino Crew', attributes: [
        'description' => 'Soft.',
        'meta_title' => 'Merino Crew | Shop',
        'is_featured' => true,
    ]);

    expect($product->description)->toBe('Soft.')
        ->and($product->meta_title)->toBe('Merino Crew | Shop')
        ->and($product->is_featured)->toBeTrue();
});

it('announces the product once it exists', function () {
    Event::fake([ProductCreated::class]);

    $product = (new CreateProduct())->handle('Merino Crew');

    Event::assertDispatched(ProductCreated::class, fn (ProductCreated $event): bool => $event->product->is($product));
});

it('resolves a product by its slug in a route', function () {
    expect((new Product())->getRouteKeyName())->toBe('slug');
});

it('scopes to a store, and scopes to nothing when there is no store', function () {
    $mine = Product::factory()->inStore(1)->create();
    $theirs = Product::factory()->inStore(2)->create();
    $unassigned = Product::factory()->create();

    // The null case is guarded rather than passed through: `where('store_id',
    // null)` compiles to `is null` and would list exactly the unassigned rows.
    expect(Product::query()->forStore(1)->orderBy('id')->pluck('id')->all())->toBe([$mine->id])
        ->and(Product::query()->forStore(null)->orderBy('id')->pluck('id')->all())
        ->toBe([$mine->id, $theirs->id, $unassigned->id]);
});
