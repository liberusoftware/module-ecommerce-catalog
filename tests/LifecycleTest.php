<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Catalog\Actions\ChangeProductStatus;
use Liberu\Ecommerce\Catalog\Actions\SetProductVisibility;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Enums\Visibility;
use Liberu\Ecommerce\Catalog\Events\ProductStatusChanged;
use Liberu\Ecommerce\Catalog\Events\ProductVisibilityChanged;
use Liberu\Ecommerce\Catalog\Exceptions\InvalidStatusTransition;
use Liberu\Ecommerce\Catalog\Models\Product;

it('admits exactly the transitions the enum lists', function (ProductStatus $from, ProductStatus $to) {
    $product = Product::factory()->create(['status' => $from]);

    expect((new ChangeProductStatus())->handle($product, $to)->status)->toBe($to);
})->with([
    'draft to active' => [ProductStatus::Draft, ProductStatus::Active],
    'draft to archived' => [ProductStatus::Draft, ProductStatus::Archived],
    'active to discontinued' => [ProductStatus::Active, ProductStatus::Discontinued],
    'active to archived' => [ProductStatus::Active, ProductStatus::Archived],
    'discontinued back to active' => [ProductStatus::Discontinued, ProductStatus::Active],
    'discontinued to archived' => [ProductStatus::Discontinued, ProductStatus::Archived],
]);

it('refuses the transitions the enum does not list', function (ProductStatus $from, ProductStatus $to) {
    $product = Product::factory()->create(['status' => $from]);

    expect(fn () => (new ChangeProductStatus())->handle($product, $to))->toThrow(InvalidStatusTransition::class);
})->with([
    // Back to draft is absent on purpose: a product that has been offered has
    // been seen and linked, and un-finishing it is how a live URL starts 404ing.
    'active back to draft' => [ProductStatus::Active, ProductStatus::Draft],
    'discontinued back to draft' => [ProductStatus::Discontinued, ProductStatus::Draft],
    'archived to active' => [ProductStatus::Archived, ProductStatus::Active],
    'archived to draft' => [ProductStatus::Archived, ProductStatus::Draft],
    'draft straight to discontinued' => [ProductStatus::Draft, ProductStatus::Discontinued],
]);

it('treats a move to the state it is already in as nothing at all', function () {
    Event::fake([ProductStatusChanged::class]);
    $product = Product::factory()->archived()->create();

    // Idempotent even from a terminal state: a retried job asking for the state
    // the row is already in is not a fault.
    expect((new ChangeProductStatus())->handle($product, ProductStatus::Archived)->status)
        ->toBe(ProductStatus::Archived);

    Event::assertNotDispatched(ProductStatusChanged::class);
});

it('carries both ends of the move', function () {
    Event::fake([ProductStatusChanged::class]);
    $product = Product::factory()->create();

    (new ChangeProductStatus())->handle($product, ProductStatus::Discontinued);

    Event::assertDispatched(
        ProductStatusChanged::class,
        fn (ProductStatusChanged $event): bool => $event->from === ProductStatus::Active && $event->to === ProductStatus::Discontinued,
    );
});

it('knows which states sell and which one is the end', function () {
    expect(ProductStatus::Active->isSellable())->toBeTrue()
        ->and(ProductStatus::Draft->isSellable())->toBeFalse()
        ->and(ProductStatus::Discontinued->isSellable())->toBeFalse()
        ->and(ProductStatus::Archived->isTerminal())->toBeTrue()
        ->and(ProductStatus::Active->isTerminal())->toBeFalse()
        ->and(ProductStatus::Draft->label())->toBe('Draft');
});

it('lets visibility go anywhere, because it describes the present', function (Visibility $from, Visibility $to) {
    $product = Product::factory()->create(['visibility' => $from]);

    expect((new SetProductVisibility())->handle($product, $to)->visibility)->toBe($to);
})->with([
    'public to hidden' => [Visibility::Public, Visibility::Hidden],
    'hidden to public' => [Visibility::Hidden, Visibility::Public],
    'public to unlisted' => [Visibility::Public, Visibility::Unlisted],
    'unlisted to public' => [Visibility::Unlisted, Visibility::Public],
]);

it('says nothing when visibility did not move', function () {
    Event::fake([ProductVisibilityChanged::class]);

    (new SetProductVisibility())->handle(Product::factory()->create(), Visibility::Public);

    Event::assertNotDispatched(ProductVisibilityChanged::class);
});

it('separates being listed from being reachable', function () {
    expect(Visibility::Public->isListed())->toBeTrue()
        ->and(Visibility::Unlisted->isListed())->toBeFalse()
        // The whole reason the middle state exists: a campaign link still works.
        ->and(Visibility::Unlisted->isReachable())->toBeTrue()
        ->and(Visibility::Hidden->isReachable())->toBeFalse()
        ->and(Visibility::Hidden->label())->toBe('Hidden');
});
