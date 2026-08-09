<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Catalog\Actions\ScheduleAvailability;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Enums\Visibility;
use Liberu\Ecommerce\Catalog\Events\ProductAvailabilityScheduled;
use Liberu\Ecommerce\Catalog\Exceptions\InvalidAvailabilityWindow;
use Liberu\Ecommerce\Catalog\Models\Product;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-15 12:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('accepts an open-ended window at either end, and one that has already run', function (?string $from, ?string $until) {
    $product = Product::factory()->create();

    $scheduled = (new ScheduleAvailability())->handle(
        $product,
        $from === null ? null : CarbonImmutable::parse($from),
        $until === null ? null : CarbonImmutable::parse($until),
    );

    expect($scheduled->available_from?->toDateString())->toBe($from)
        ->and($scheduled->available_until?->toDateString())->toBe($until);
})->with([
    'no window at all' => [null, null],
    'opens, never closes' => ['2026-06-01', null],
    'closes, always was open' => [null, '2026-07-01'],
    'both ends' => ['2026-06-01', '2026-07-01'],
    // Accepted on purpose: it is how a campaign gets recorded after the fact,
    // and refusing it only teaches operators to enter a lie.
    'entirely in the past' => ['2020-01-01', '2020-02-01'],
]);

it('refuses a window that closes before it opens', function () {
    expect(fn () => (new ScheduleAvailability())->handle(
        Product::factory()->create(),
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-06-01'),
    ))->toThrow(InvalidAvailabilityWindow::class);
});

it('refuses a window with no width', function () {
    $instant = CarbonImmutable::parse('2026-07-01');

    expect(fn () => (new ScheduleAvailability())->handle(Product::factory()->create(), $instant, $instant))
        ->toThrow(InvalidAvailabilityWindow::class);
});

it('reports both ends when a window is scheduled, so a cancellation is not silent', function () {
    Event::fake([ProductAvailabilityScheduled::class]);

    (new ScheduleAvailability())->handle(Product::factory()->create(), null, null);

    Event::assertDispatched(
        ProductAvailabilityScheduled::class,
        fn (ProductAvailabilityScheduled $event): bool => $event->from === null && $event->until === null,
    );
});

it('decides availability from the status, the window and the moment', function (array $state, string $at, bool $expected) {
    $product = Product::factory()->create($state);

    expect($product->isAvailableOn(null, CarbonImmutable::parse($at)))->toBe($expected);
})->with([
    'active, no window' => [['status' => ProductStatus::Active], '2026-06-15', true],
    'hidden is as unreachable as a draft' => [['visibility' => Visibility::Hidden], '2026-06-15', false],
    'draft, no window' => [['status' => ProductStatus::Draft], '2026-06-15', false],
    'discontinued' => [['status' => ProductStatus::Discontinued], '2026-06-15', false],
    'archived' => [['status' => ProductStatus::Archived], '2026-06-15', false],
    'before it opens' => [['available_from' => '2026-07-01'], '2026-06-15', false],
    'on the moment it opens' => [['available_from' => '2026-06-15 12:00:00'], '2026-06-15 12:00:00', true],
    'after it opens' => [['available_from' => '2026-06-01'], '2026-06-15', true],
    'before it closes' => [['available_until' => '2026-07-01'], '2026-06-15', true],
    // The closing edge is exclusive: "until the 1st" means the 1st is over.
    'on the moment it closes' => [['available_until' => '2026-06-15 12:00:00'], '2026-06-15 12:00:00', false],
    'after it closes' => [['available_until' => '2026-06-01'], '2026-06-15', false],
]);

it('keeps hidden and unlisted products out of listings while leaving them reachable', function () {
    $public = Product::factory()->create();
    $unlisted = Product::factory()->unlisted()->create();
    $hidden = Product::factory()->hidden()->create();

    expect($public->isListedOn())->toBeTrue()
        ->and($unlisted->isListedOn())->toBeFalse()
        ->and($hidden->isListedOn())->toBeFalse()
        // The one difference between the two middle states, and the only
        // reason `unlisted` exists: a campaign link still resolves to it,
        // while `hidden` is as unreachable as a draft.
        ->and($unlisted->isAvailableOn())->toBeTrue()
        ->and($hidden->isAvailableOn())->toBeFalse();
});

it('gives the list query and the single-record check the same answer', function () {
    // They must: `isAvailableOn` delegates to the scope rather than deciding
    // again in PHP, and this is the test that says so out loud.
    Product::factory()->create();
    Product::factory()->draft()->create();
    Product::factory()->create(['available_from' => '2026-07-01']);
    $expected = Product::query()->get()->filter(fn (Product $product): bool => $product->isAvailableOn())->pluck('id')->sort()->values()->all();

    expect(Product::query()->availableOn()->orderBy('id')->pluck('id')->all())->toBe($expected);
});

it('never lists a soft-deleted product', function () {
    $product = Product::factory()->create();
    $product->delete();

    expect(Product::query()->availableOn()->count())->toBe(0)
        ->and($product->isAvailableOn())->toBeFalse();
});

it('applies the visibility filter on top of availability, not instead of it', function () {
    Product::factory()->draft()->create();
    $listed = Product::factory()->create();

    expect(Product::query()->listedOn()->pluck('id')->all())->toBe([$listed->id]);
});

it('keeps the visibility enum and the column in step', function () {
    $product = Product::factory()->create(['visibility' => Visibility::Unlisted]);

    expect($product->fresh()->visibility)->toBe(Visibility::Unlisted);
});
