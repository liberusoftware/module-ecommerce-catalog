<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\Catalog\Actions\AddProductToCollection;
use Liberu\Ecommerce\Catalog\Actions\AddVariant;
use Liberu\Ecommerce\Catalog\Actions\CreateBrand;
use Liberu\Ecommerce\Catalog\Actions\CreateCategory;
use Liberu\Ecommerce\Catalog\Actions\CreateCollection;
use Liberu\Ecommerce\Catalog\Actions\CreateVendor;
use Liberu\Ecommerce\Catalog\Actions\PublishToChannel;
use Liberu\Ecommerce\Catalog\Actions\SyncProductTags;
use Liberu\Ecommerce\Catalog\Data\CollectionData;
use Liberu\Ecommerce\Catalog\Data\ProductData;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Queries\CategoryQuery;
use Liberu\Ecommerce\Catalog\Queries\CollectionQuery;
use Liberu\Ecommerce\Catalog\Queries\ProductQuery;

it('serialises a product without exposing a model', function () {
    $brand = (new CreateBrand())->handle('Acme');
    $vendor = (new CreateVendor())->handle('Supplier');
    $category = (new CreateCategory())->handle('Knitwear');
    $product = Product::factory()->create([
        'team_id' => 7,
        'store_id' => 3,
        'brand_id' => $brand->id,
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
    ]);
    (new AddVariant())->handle($product, 'TEE-S', 'Small', ['Small']);
    (new SyncProductTags())->handle($product, ['Wool']);
    (new PublishToChannel())->handle($product, 4);

    $data = (new ProductQuery())->find($product->id);

    expect($data)->toBeInstanceOf(ProductData::class)
        ->and($data->teamId)->toBe(7)
        ->and($data->storeId)->toBe(3)
        ->and($data->brand->slug)->toBe('acme')
        ->and($data->vendor->slug)->toBe('supplier')
        ->and($data->category->slug)->toBe('knitwear')
        ->and($data->tags)->toBe(['wool'])
        ->and($data->variants[0]->sku)->toBe('TEE-S')
        ->and($data->variants[0]->optionValues)->toBe(['Small'])
        ->and($data->publications[0]->channelId)->toBe(4)
        ->and($data->publications[0]->live)->toBeTrue();
});

it('says nothing about price or stock, because it owns neither', function () {
    $product = Product::factory()->create(['description' => 'A jumper.', 'name' => 'Jumper']);
    (new AddVariant())->handle($product, 'TEE-S');

    $json = json_encode((new ProductQuery())->find($product->id));

    // Pricing and Inventory Ledger extend a product through their own tables
    // keyed on this id. A price field here would make this package the owner of
    // a rule it does not enforce.
    expect($json)->not->toContain('price')
        ->not->toContain('inventory')
        ->not->toContain('stock')
        ->and(json_decode($json, true)['id'])->toBe($product->id);
});

it('returns nothing rather than a half-built record for an id that is not there', function () {
    expect((new ProductQuery())->find(999999))->toBeNull()
        ->and((new ProductQuery())->findBySlug('nope'))->toBeNull()
        ->and((new CategoryQuery())->find(999999))->toBeNull()
        ->and((new CategoryQuery())->findBySlug('nope'))->toBeNull()
        ->and((new CollectionQuery())->findBySlug('nope'))->toBeNull();
});

it('leaves a relation empty rather than lazily fetching it behind the caller’s back', function () {
    // A read model that lazy-loads turns a paginated list into six queries per
    // row. The queries in this module eager load; a hand-built one gets an
    // honest empty list.
    $product = Product::factory()->create();
    (new AddVariant())->handle($product, 'TEE-S');

    $data = ProductData::from(Product::query()->find($product->id));

    expect($data->variants)->toBe([])
        ->and($data->tags)->toBe([])
        ->and($data->brand)->toBeNull();
});

it('gives an operator every product and a shopper only what is listed', function () {
    $listed = Product::factory()->inStore(1)->create();
    Product::factory()->inStore(1)->draft()->create();
    Product::factory()->inStore(1)->hidden()->create();
    Product::factory()->inStore(2)->create();

    expect((new ProductQuery())->paginate(1)->total())->toBe(3)
        ->and((new ProductQuery())->storefront(1)->total())->toBe(1)
        ->and((new ProductQuery())->storefront(1)->items()[0]->id)->toBe($listed->id);
});

it('filters a storefront to the channel the shopper is on', function () {
    $onOne = Product::factory()->create();
    $onTwo = Product::factory()->create();
    (new PublishToChannel())->handle($onOne, 1);
    (new PublishToChannel())->handle($onTwo, 2);

    expect((new ProductQuery())->storefront(null, 1)->total())->toBe(1)
        ->and((new ProductQuery())->storefront(null, 1)->items()[0]->id)->toBe($onOne->id);
});

it('answers a storefront question at a stated moment, not only now', function () {
    $product = Product::factory()->create(['available_from' => '2026-07-01']);
    (new PublishToChannel())->handle($product, 1);

    expect((new ProductQuery())->storefront(null, 1, CarbonImmutable::parse('2026-06-01'))->total())->toBe(0)
        ->and((new ProductQuery())->storefront(null, 1, CarbonImmutable::parse('2026-07-02'))->total())->toBe(1);
});

it('serves an unlisted product by direct link but never in a listing', function () {
    $unlisted = Product::factory()->unlisted()->create();
    (new PublishToChannel())->handle($unlisted, 1);

    expect((new ProductQuery())->storefront(null, 1)->total())->toBe(0)
        ->and((new ProductQuery())->findOnChannel($unlisted->slug, 1))->not->toBeNull();
});

it('refuses a direct link to a hidden or unpublished product', function () {
    $hidden = Product::factory()->hidden()->create();
    (new PublishToChannel())->handle($hidden, 1);
    $unpublished = Product::factory()->create();

    expect((new ProductQuery())->findOnChannel($hidden->slug, 1))->toBeNull()
        ->and((new ProductQuery())->findOnChannel($unpublished->slug, 1))->toBeNull();
});

it('shows a branch category’s descendants, not an empty page', function () {
    $outerwear = (new CreateCategory())->handle('Outerwear');
    $parkas = (new CreateCategory())->handle('Parkas', $outerwear->id);
    Product::factory()->create(['category_id' => $parkas->id]);
    Product::factory()->create(['category_id' => $outerwear->id]);
    Product::factory()->create();

    expect((new ProductQuery())->inCategory($outerwear->id)->total())->toBe(2)
        ->and((new ProductQuery())->inCategory($parkas->id)->total())->toBe(1)
        // A category that is not there narrows to itself rather than matching
        // everything, which is the failure mode worth naming.
        ->and((new ProductQuery())->inCategory(999999)->total())->toBe(0);
});

it('builds the whole tree from one query', function () {
    $men = (new CreateCategory())->handle('Men');
    $shirts = (new CreateCategory())->handle('Shirts', $men->id);
    (new CreateCategory())->handle('Oxford', $shirts->id);
    (new CreateCategory())->handle('Women');

    $tree = (new CategoryQuery())->tree();

    expect($tree)->toHaveCount(2)
        ->and($tree[0]->slug)->toBe('men')
        ->and($tree[0]->children[0]->slug)->toBe('shirts')
        ->and($tree[0]->children[0]->children[0]->slug)->toBe('oxford')
        ->and($tree[1]->children)->toBe([])
        ->and($tree[0]->toArray()['children'][0]['name'])->toBe('Shirts');
});

it('scopes the tree to a team when asked', function () {
    (new CreateCategory())->handle('Mine', null, 7);
    (new CreateCategory())->handle('Theirs', null, 8);

    expect((new CategoryQuery())->tree(7))->toHaveCount(1)
        ->and((new CategoryQuery())->tree())->toHaveCount(2);
});

it('walks a breadcrumb from the root down', function () {
    $men = (new CreateCategory())->handle('Men');
    $shirts = (new CreateCategory())->handle('Shirts', $men->id);
    $oxford = (new CreateCategory())->handle('Oxford', $shirts->id);

    expect(array_map(fn ($node): string => $node->slug, (new CategoryQuery())->breadcrumb($oxford->id)))
        ->toBe(['men', 'shirts', 'oxford'])
        ->and((new CategoryQuery())->breadcrumb(999999))->toBe([]);
});

it('stops a breadcrumb walking a tree somebody corrupted around the action', function () {
    // `MoveCategory` refuses cycles. A row edited around it must still not be
    // able to hang a page render, so the walk is bounded by the row count.
    $a = Category::factory()->create();
    $b = Category::factory()->under($a)->create();
    Category::query()->whereKey($a->id)->update(['parent_category_id' => $b->id]);

    expect((new CategoryQuery())->breadcrumb($b->id))->not->toBeEmpty();
});

it('counts a collection’s products and lists them in merchandised order', function () {
    $collection = (new CreateCollection())->handle('Summer', 7);
    $first = Product::factory()->create();
    $second = Product::factory()->create();
    $hidden = Product::factory()->hidden()->create();
    (new AddProductToCollection())->handle($collection, $second, 1);
    (new AddProductToCollection())->handle($collection, $first, 2);
    (new AddProductToCollection())->handle($collection, $hidden, 3);

    $data = (new CollectionQuery())->findBySlug('summer');
    $products = (new CollectionQuery())->products($collection->id);

    expect($data->productCount)->toBe(3)
        ->and((new CollectionQuery())->paginate(7)->total())->toBe(1)
        ->and((new CollectionQuery())->paginate(8)->total())->toBe(0)
        // Merchandised order kept, and the hidden one dropped: a collection is
        // a shopper-facing list, not a bag of ids.
        ->and(array_map(fn ($product): int => $product->id, $products))->toBe([$second->id, $first->id]);
});

it('counts a collection loaded without withCount rather than reporting zero', function () {
    $collection = (new CreateCollection())->handle('Summer');
    (new AddProductToCollection())->handle($collection, Product::factory()->create());

    expect(CollectionData::from($collection)->productCount)->toBe(1);
});

it('round-trips every read model through json', function () {
    $product = Product::factory()->create(['available_from' => '2026-01-01', 'available_until' => '2026-12-31']);
    (new AddVariant())->handle($product, 'TEE-S');
    (new PublishToChannel())->handle($product, 1);

    $array = json_decode(json_encode((new ProductQuery())->find($product->id)), true);

    expect($array['status'])->toBe('active')
        ->and($array['visibility'])->toBe('public')
        ->and($array['available_from'])->toStartWith('2026-01-01')
        ->and($array['available_until'])->toStartWith('2026-12-31')
        ->and($array['variants'][0]['sku'])->toBe('TEE-S')
        ->and($array['publications'][0]['channel_id'])->toBe(1);
});
