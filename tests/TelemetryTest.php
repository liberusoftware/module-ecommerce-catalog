<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Liberu\Ecommerce\Catalog\Actions\AddProductToCollection;
use Liberu\Ecommerce\Catalog\Actions\AddVariant;
use Liberu\Ecommerce\Catalog\Actions\ChangeProductStatus;
use Liberu\Ecommerce\Catalog\Actions\CreateBrand;
use Liberu\Ecommerce\Catalog\Actions\CreateCategory;
use Liberu\Ecommerce\Catalog\Actions\CreateCollection;
use Liberu\Ecommerce\Catalog\Actions\CreateProduct;
use Liberu\Ecommerce\Catalog\Actions\CreateVendor;
use Liberu\Ecommerce\Catalog\Actions\MoveCategory;
use Liberu\Ecommerce\Catalog\Actions\PublishToChannel;
use Liberu\Ecommerce\Catalog\Actions\RemoveProductFromCollection;
use Liberu\Ecommerce\Catalog\Actions\RemoveVariant;
use Liberu\Ecommerce\Catalog\Actions\ScheduleAvailability;
use Liberu\Ecommerce\Catalog\Actions\SetProductOption;
use Liberu\Ecommerce\Catalog\Actions\SetProductVisibility;
use Liberu\Ecommerce\Catalog\Actions\SyncProductTags;
use Liberu\Ecommerce\Catalog\Actions\UnpublishFromChannel;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Enums\Visibility;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * Capture what the logger wrote, in order.
 *
 * The reader is a long closure with an explicit `use (&$records)` rather than
 * an arrow function: `fn` captures by value at the point it is defined, so it
 * would hand back the empty array this starts as and never see anything the
 * listener appended.
 */
function captureLog(): Closure
{
    $records = [];

    Log::listen(function ($record) use (&$records) {
        $records[] = ['level' => $record->level, 'message' => $record->message, 'context' => $record->context];
    });

    return function () use (&$records): array {
        return $records;
    };
}

beforeEach(function () {
    config()->set('catalog.telemetry.enabled', true);
    config()->set('catalog.telemetry.channel', null);
});

it('writes nothing at all until a deployment asks for it', function () {
    config()->set('catalog.telemetry.enabled', false);
    $records = captureLog();

    (new CreateProduct())->handle('Merino Crew');

    expect($records())->toBe([]);
});

it('records each domain event under a stable name a query can key on', function (Closure $act, string $event) {
    $records = captureLog();

    $act();

    expect(collect($records())->pluck('context.event'))->toContain('catalog.'.$event);
})->with([
    // One level of closure, not two. Pest hands a closure inside a dataset row
    // through untouched rather than calling it, so a closure returning a
    // closure would assert on an action that never ran.
    'product created' => [fn () => (new CreateProduct())->handle('Merino Crew'), 'product.created'],
    'status' => [fn () => (new ChangeProductStatus())->handle(Product::factory()->draft()->create(), ProductStatus::Active), 'product.status_changed'],
    'visibility' => [fn () => (new SetProductVisibility())->handle(Product::factory()->create(), Visibility::Hidden), 'product.visibility_changed'],
    'availability' => [fn () => (new ScheduleAvailability())->handle(Product::factory()->create(), null, null), 'product.availability_scheduled'],
    'published' => [fn () => (new PublishToChannel())->handle(Product::factory()->create(), 1), 'product.published'],
    'variant added' => [fn () => (new AddVariant())->handle(Product::factory()->create(), 'TEE-S'), 'variant.added'],
    'variant removed' => [fn () => (new RemoveVariant())->handle((new AddVariant())->handle(Product::factory()->create(), 'TEE-S')), 'variant.removed'],
    'option set' => [fn () => (new SetProductOption())->handle(Product::factory()->create(), 'Size', ['S']), 'product.option_set'],
    'tags' => [fn () => (new SyncProductTags())->handle(Product::factory()->create(), ['Wool']), 'product.tags_changed'],
    'collection created' => [fn () => (new CreateCollection())->handle('Summer'), 'collection.created'],
    'added to collection' => [fn () => (new AddProductToCollection())->handle((new CreateCollection())->handle('Summer'), Product::factory()->create()), 'collection.product_added'],
    'category created' => [fn () => (new CreateCategory())->handle('Knitwear'), 'category.created'],
    'brand created' => [fn () => (new CreateBrand())->handle('Acme'), 'brand.created'],
    'vendor created' => [fn () => (new CreateVendor())->handle('Supplier'), 'vendor.created'],
]);

it('records a product leaving a collection', function () {
    $collection = (new CreateCollection())->handle('Summer');
    $product = Product::factory()->create();
    (new AddProductToCollection())->handle($collection, $product);
    $records = captureLog();

    (new RemoveProductFromCollection())->handle($collection, $product);

    expect(collect($records())->pluck('context.event'))->toContain('catalog.collection.product_removed');
});

it('raises the level when a product stops selling, and not when it starts', function () {
    $product = Product::factory()->draft()->create();
    $records = captureLog();

    (new ChangeProductStatus())->handle($product, ProductStatus::Active);
    (new ChangeProductStatus())->handle($product->fresh(), ProductStatus::Discontinued);

    $levels = collect($records())->where('context.event', 'catalog.product.status_changed')->pluck('level')->all();

    expect($levels)->toBe(['info', 'warning']);
});

it('raises the level when a product leaves the listings', function () {
    $records = captureLog();

    (new SetProductVisibility())->handle(Product::factory()->create(), Visibility::Unlisted);
    (new SetProductVisibility())->handle(Product::factory()->hidden()->create(), Visibility::Public);

    $levels = collect($records())->where('context.event', 'catalog.product.visibility_changed')->pluck('level')->all();

    expect($levels)->toBe(['warning', 'info']);
});

it('flags the records that take something away from shoppers', function () {
    $product = Product::factory()->create();
    (new PublishToChannel())->handle($product, 1);
    $variant = (new AddVariant())->handle($product, 'TEE-S');
    $child = Category::factory()->under(Category::factory()->create())->create();
    $records = captureLog();

    (new UnpublishFromChannel())->handle($product, 1);
    (new RemoveVariant())->handle($variant);
    (new MoveCategory())->handle($child, null);

    $flagged = collect($records())->whereIn('context.event', [
        'catalog.product.unpublished',
        'catalog.variant.removed',
        'catalog.category.moved',
    ]);

    expect($flagged)->toHaveCount(3)
        ->and($flagged->pluck('level')->unique()->all())->toBe(['warning']);
});

it('never writes an option’s values, only how many there were', function () {
    // An option's choices are merchant content of unbounded length, and a log
    // line is the wrong place to keep a copy of it.
    $records = captureLog();

    (new SetProductOption())->handle(Product::factory()->create(), 'Size', ['Enormous', 'Colossal']);

    $record = collect($records())->firstWhere('context.event', 'catalog.product.option_set');

    expect(json_encode($record))->not->toContain('Enormous')
        ->and($record['context']['option'])->toBe('Size')
        ->and($record['context']['value_count'])->toBe(2);
});

it('carries the identifiers an operator searches by', function () {
    $records = captureLog();

    $product = (new CreateProduct())->handle('Merino Crew', teamId: 7, storeId: 3);

    $record = collect($records())->firstWhere('context.event', 'catalog.product.created');

    expect($record['context']['product_id'])->toBe($product->id)
        ->and($record['context']['team_id'])->toBe(7)
        ->and($record['context']['store_id'])->toBe(3)
        ->and($record['context']['slug'])->toBe('merino-crew');
});

it('says which channel a publication window belongs to', function () {
    $records = captureLog();

    (new PublishToChannel())->handle(Product::factory()->create(), 42);

    $record = collect($records())->firstWhere('context.event', 'catalog.product.published');

    expect($record['context']['channel_id'])->toBe(42)
        ->and($record['context']['published_at'])->toBeNull();
});

it('reports what moved on a tag change rather than the whole set', function () {
    $product = Product::factory()->create();
    (new SyncProductTags())->handle($product, ['Wool']);
    $records = captureLog();

    (new SyncProductTags())->handle($product, ['Waterproof']);

    $record = collect($records())->firstWhere('context.event', 'catalog.product.tags_changed');

    expect($record['context']['attached'])->toBe(['waterproof'])
        ->and($record['context']['detached'])->toBe(['wool']);
});
