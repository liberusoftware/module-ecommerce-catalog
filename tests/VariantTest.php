<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Catalog\Actions\AddVariant;
use Liberu\Ecommerce\Catalog\Actions\RemoveVariant;
use Liberu\Ecommerce\Catalog\Actions\SetProductOption;
use Liberu\Ecommerce\Catalog\Events\ProductOptionSet;
use Liberu\Ecommerce\Catalog\Events\VariantAdded;
use Liberu\Ecommerce\Catalog\Events\VariantRemoved;
use Liberu\Ecommerce\Catalog\Exceptions\SkuAlreadyClaimed;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductOption;
use Liberu\Ecommerce\Catalog\Models\ProductVariant;

it('appends each variant rather than asking the caller to count', function () {
    $product = Product::factory()->create();

    $first = (new AddVariant())->handle($product, 'TEE-S');
    $second = (new AddVariant())->handle($product, 'TEE-M');
    $third = (new AddVariant())->handle($product, 'TEE-L');

    expect([$first->position, $second->position, $third->position])->toBe([1, 2, 3]);
});

it('spreads option values across the three axes in order', function () {
    $variant = (new AddVariant())->handle(Product::factory()->create(), 'TEE-RS', 'Red / Small', ['Red', 'Small']);

    expect($variant->option1)->toBe('Red')
        ->and($variant->option2)->toBe('Small')
        ->and($variant->option3)->toBeNull()
        // The unused axis is dropped rather than rendered as an empty segment.
        ->and($variant->optionValues())->toBe(['Red', 'Small']);
});

it('refuses a SKU another variant already holds, with a sentence naming it', function () {
    (new AddVariant())->handle(Product::factory()->create(), 'TEE-S');

    expect(fn () => (new AddVariant())->handle(Product::factory()->create(), 'TEE-S'))
        ->toThrow(SkuAlreadyClaimed::class, 'TEE-S');
});

it('allows any number of variants with no SKU at all', function () {
    $product = Product::factory()->create();

    (new AddVariant())->handle($product, null, 'Default');
    (new AddVariant())->handle($product, null, 'Other');

    expect($product->variants()->count())->toBe(2);
});

it('takes the shipping attributes it was given', function () {
    $variant = (new AddVariant())->handle(Product::factory()->create(), 'TEE-S', null, [], [
        'weight' => '0.30',
        'weight_unit' => 'kg',
        'requires_shipping' => false,
        'barcode' => '5012345678900',
    ]);

    expect($variant->requires_shipping)->toBeFalse()
        ->and($variant->barcode)->toBe('5012345678900');
});

it('defaults a variant to something shippable even though the caller said nothing', function () {
    // `create()` does not read a column default back, so these are restated on
    // the model as well as in the migration.
    $variant = (new AddVariant())->handle(Product::factory()->create(), 'TEE-S');

    expect($variant->requires_shipping)->toBeTrue()
        ->and($variant->weight_unit)->toBe('kg');
});

it('hard deletes a variant so its SKU comes free again', function () {
    $product = Product::factory()->create();
    $variant = (new AddVariant())->handle($product, 'TEE-S');

    (new RemoveVariant())->handle($variant);

    expect(ProductVariant::query()->count())->toBe(0)
        ->and((new AddVariant())->handle($product, 'TEE-S')->sku)->toBe('TEE-S');
});

it('carries the ids on the way out, because the row is gone by then', function () {
    Event::fake([VariantAdded::class, VariantRemoved::class]);
    $product = Product::factory()->create();
    $variant = (new AddVariant())->handle($product, 'TEE-S');

    (new RemoveVariant())->handle($variant);

    Event::assertDispatched(VariantAdded::class, fn (VariantAdded $event): bool => $event->variant->sku === 'TEE-S');
    Event::assertDispatched(VariantRemoved::class, fn (VariantRemoved $event): bool => $event->sku === 'TEE-S' && $event->productId === $product->id);
});

it('declares an option axis and replaces its choices in place', function () {
    $product = Product::factory()->create();

    (new SetProductOption())->handle($product, 'Size', ['Small', 'Medium']);
    $option = (new SetProductOption())->handle($product, 'Size', ['Small', 'Medium', 'Large']);

    expect(ProductOption::query()->count())->toBe(1)
        ->and($option->values)->toBe(['Small', 'Medium', 'Large']);
});

it('de-duplicates and re-indexes option values so the JSON stays a list', function () {
    // A JSON column with holes in its keys decodes to an object rather than an
    // array, and every consumer then has to handle both shapes.
    $option = (new SetProductOption())->handle(Product::factory()->create(), 'Size', ['Small', 'Small', 'Large']);

    expect($option->values)->toBe(['Small', 'Large'])
        ->and(array_is_list($option->fresh()->values))->toBeTrue();
});

it('appends option axes in declaration order', function () {
    $product = Product::factory()->create();

    $size = (new SetProductOption())->handle($product, 'Size', ['S']);
    $colour = (new SetProductOption())->handle($product, 'Colour', ['Red']);

    expect([$size->position, $colour->position])->toBe([1, 2]);
});

it('announces an option being set', function () {
    Event::fake([ProductOptionSet::class]);

    (new SetProductOption())->handle(Product::factory()->create(), 'Size', ['S']);

    Event::assertDispatched(ProductOptionSet::class, fn (ProductOptionSet $event): bool => $event->option->name === 'Size');
});
