<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Catalog\Actions\PublishToChannel;
use Liberu\Ecommerce\Catalog\Actions\UnpublishFromChannel;
use Liberu\Ecommerce\Catalog\Events\ProductPublished;
use Liberu\Ecommerce\Catalog\Events\ProductUnpublished;
use Liberu\Ecommerce\Catalog\Exceptions\InvalidStatusTransition;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductPublication;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-15 12:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('publishes to a channel it has never heard of, because a channel is an id here', function () {
    $product = Product::factory()->create();

    $publication = (new PublishToChannel())->handle($product, 4242);

    expect($publication->channel_id)->toBe(4242)
        ->and($publication->published_at)->toBeNull()
        ->and($publication->isLive())->toBeTrue();
});

it('rewrites the window instead of failing when the same import runs twice', function () {
    $product = Product::factory()->create();

    (new PublishToChannel())->handle($product, 1, CarbonImmutable::parse('2026-07-01'));
    (new PublishToChannel())->handle($product, 1, CarbonImmutable::parse('2026-08-01'));

    expect(ProductPublication::query()->where('product_id', $product->id)->count())->toBe(1)
        ->and(ProductPublication::query()->first()->published_at->toDateString())->toBe('2026-08-01');
});

it('refuses to put an archived product back in front of shoppers', function () {
    expect(fn () => (new PublishToChannel())->handle(Product::factory()->archived()->create(), 1))
        ->toThrow(InvalidStatusTransition::class);
});

it('lets a draft be staged on a channel ahead of going live', function () {
    // Publishing a draft is allowed: a merchant stages a season and then flips
    // the products. The product is still not available, which is the control.
    $product = Product::factory()->draft()->create();

    (new PublishToChannel())->handle($product, 1);

    expect($product->isAvailableOn(1))->toBeFalse();
});

it('only counts a product as available on a channel it is actually published to', function () {
    $product = Product::factory()->create();
    (new PublishToChannel())->handle($product, 1);

    expect($product->isAvailableOn(1))->toBeTrue()
        ->and($product->isAvailableOn(2))->toBeFalse()
        // No channel asked means the catalogue-wide question, which publication
        // has no bearing on.
        ->and($product->isAvailableOn())->toBeTrue();
});

it('honours the publication window separately from the product window', function (?string $from, ?string $until, bool $expected) {
    $product = Product::factory()->create();

    (new PublishToChannel())->handle(
        $product,
        1,
        $from === null ? null : CarbonImmutable::parse($from),
        $until === null ? null : CarbonImmutable::parse($until),
    );

    expect($product->isAvailableOn(1))->toBe($expected);
})->with([
    'open ended' => [null, null, true],
    'not started yet' => ['2026-07-01', null, false],
    'started' => ['2026-06-01', null, true],
    'already ended' => [null, '2026-06-01', false],
    'still running' => [null, '2026-07-01', true],
    'window around now' => ['2026-06-01', '2026-07-01', true],
    'window entirely later' => ['2026-07-01', '2026-08-01', false],
]);

it('lets the product window close a publication that is still open', function () {
    // Two windows, and the narrower one wins — the product's says whether the
    // thing is sellable at all, the publication's says whether this storefront
    // carries it.
    $product = Product::factory()->create(['available_until' => '2026-06-01']);
    (new PublishToChannel())->handle($product, 1);

    expect($product->isAvailableOn(1))->toBeFalse();
});

it('closes a live publication rather than deleting it, so the dates it ran survive', function () {
    $product = Product::factory()->create();
    (new PublishToChannel())->handle($product, 1);

    (new UnpublishFromChannel())->handle($product, 1);

    $publication = ProductPublication::query()->where('product_id', $product->id)->first();

    expect($publication)->not->toBeNull()
        ->and($publication->unpublished_at->toDateTimeString())->toBe('2026-06-15 12:00:00')
        ->and($publication->isLive())->toBeFalse()
        ->and($product->isAvailableOn(1))->toBeFalse();
});

it('deletes a publication that never started, because nothing happened', function () {
    $product = Product::factory()->create();
    (new PublishToChannel())->handle($product, 1, CarbonImmutable::parse('2026-07-01'));

    (new UnpublishFromChannel())->handle($product, 1);

    expect(ProductPublication::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('is silent when the product was not on the channel to begin with', function () {
    Event::fake([ProductUnpublished::class]);

    (new UnpublishFromChannel())->handle(Product::factory()->create(), 99);

    Event::assertNotDispatched(ProductUnpublished::class);
});

it('announces both ends of a publication', function () {
    Event::fake([ProductPublished::class, ProductUnpublished::class]);
    $product = Product::factory()->create();

    (new PublishToChannel())->handle($product, 7);
    (new UnpublishFromChannel())->handle($product, 7);

    Event::assertDispatched(ProductPublished::class, fn (ProductPublished $event): bool => $event->publication->channel_id === 7);
    Event::assertDispatched(ProductUnpublished::class, fn (ProductUnpublished $event): bool => $event->channelId === 7);
});

it('gives the in-memory liveness check and the query the same answer', function () {
    // `isLive()` decides in PHP and `scopeLive()` decides in SQL. Two
    // implementations of one rule, so this is the test that stops them drifting.
    $product = Product::factory()->create();
    (new PublishToChannel())->handle($product, 1, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-07-01'));
    (new PublishToChannel())->handle($product, 2, CarbonImmutable::parse('2026-07-01'));
    (new PublishToChannel())->handle($product, 3, null, CarbonImmutable::parse('2026-06-01'));
    (new PublishToChannel())->handle($product, 4);

    $inMemory = ProductPublication::query()->get()
        ->filter(fn (ProductPublication $publication): bool => $publication->isLive())
        ->pluck('channel_id')->sort()->values()->all();

    expect(ProductPublication::query()->live()->orderBy('channel_id')->pluck('channel_id')->all())
        ->toBe($inMemory)
        ->and($inMemory)->toBe([1, 4]);
});
