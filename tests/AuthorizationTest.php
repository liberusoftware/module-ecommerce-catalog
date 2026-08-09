<?php

declare(strict_types=1);

use Liberu\Ecommerce\Catalog\Models\Brand;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;
use Liberu\Ecommerce\Catalog\Models\Vendor;
use Liberu\Ecommerce\Catalog\Services\CatalogAccess;
use Liberu\PackageTestbench\TestUser;

/** An actor working in a team, the way the team switcher leaves them. */
function actorInTeam(?int $teamId): TestUser
{
    $user = TestUser::factory()->create();
    $user->current_team_id = $teamId;

    return $user;
}

it('lets a merchant act on their own product', function () {
    $actor = actorInTeam(7);
    $product = Product::factory()->ownedBy(7)->create();

    expect($actor->can('view', $product))->toBeTrue()
        ->and($actor->can('update', $product))->toBeTrue()
        ->and($actor->can('publish', $product))->toBeTrue()
        ->and($actor->can('changeStatus', $product))->toBeTrue()
        ->and($actor->can('manageVariants', $product))->toBeTrue()
        ->and($actor->can('viewAny', Product::class))->toBeTrue()
        ->and($actor->can('create', Product::class))->toBeTrue();
});

it('refuses another merchant’s product outright', function () {
    $actor = actorInTeam(7);
    $theirs = Product::factory()->ownedBy(8)->create();

    expect($actor->can('view', $theirs))->toBeFalse()
        ->and($actor->can('update', $theirs))->toBeFalse()
        ->and($actor->can('delete', $theirs))->toBeFalse()
        ->and($actor->can('publish', $theirs))->toBeFalse()
        ->and($actor->can('changeStatus', $theirs))->toBeFalse();
});

it('refuses a product that belongs to nobody, which is stricter than the read scope on purpose', function () {
    $actor = actorInTeam(7);
    $orphan = Product::factory()->create(['team_id' => null]);

    expect($actor->can('view', $orphan))->toBeFalse()
        ->and($actor->can('update', $orphan))->toBeFalse();
});

it('refuses an actor with no team at all', function () {
    $actor = actorInTeam(null);
    $product = Product::factory()->ownedBy(7)->create();

    expect($actor->can('viewAny', Product::class))->toBeFalse()
        ->and($actor->can('create', Product::class))->toBeFalse()
        ->and($actor->can('view', $product))->toBeFalse();
});

it('will not let an archived product be edited, published or moved again', function () {
    $actor = actorInTeam(7);
    $archived = Product::factory()->ownedBy(7)->archived()->create();

    expect($actor->can('update', $archived))->toBeFalse()
        ->and($actor->can('publish', $archived))->toBeFalse()
        ->and($actor->can('changeStatus', $archived))->toBeFalse()
        ->and($actor->can('manageVariants', $archived))->toBeFalse();
});

it('only lets a draft be deleted, because anything offered gets archived instead', function () {
    $actor = actorInTeam(7);

    expect($actor->can('delete', Product::factory()->ownedBy(7)->draft()->create()))->toBeTrue()
        ->and($actor->can('delete', Product::factory()->ownedBy(7)->create()))->toBeFalse()
        ->and($actor->can('delete', Product::factory()->ownedBy(7)->discontinued()->create()))->toBeFalse();
});

it('applies one ownership rule to everything a product hangs from', function (string $model) {
    $actor = actorInTeam(7);
    $mine = $model::factory()->ownedBy(7)->create();
    $theirs = $model::factory()->ownedBy(8)->create();
    $orphan = $model::factory()->create();

    expect($actor->can('view', $mine))->toBeTrue()
        ->and($actor->can('update', $mine))->toBeTrue()
        ->and($actor->can('delete', $mine))->toBeTrue()
        ->and($actor->can('view', $theirs))->toBeFalse()
        ->and($actor->can('update', $theirs))->toBeFalse()
        ->and($actor->can('delete', $theirs))->toBeFalse()
        // The orphan case, asserted for each of the four rather than trusted:
        // four separate policies is four places to forget it in three of them,
        // which is why there is one.
        ->and($actor->can('view', $orphan))->toBeFalse()
        ->and($actor->can('viewAny', $model))->toBeTrue()
        ->and($actor->can('create', $model))->toBeTrue();
})->with([
    'categories' => [Category::class],
    'collections' => [ProductCollection::class],
    'brands' => [Brand::class],
    'vendors' => [Vendor::class],
]);

it('denies the taxonomy outright to an actor with no team', function (string $model) {
    $actor = actorInTeam(null);

    expect($actor->can('viewAny', $model))->toBeFalse()
        ->and($actor->can('create', $model))->toBeFalse()
        ->and($actor->can('view', $model::factory()->ownedBy(7)->create()))->toBeFalse();
})->with([
    'categories' => [Category::class],
    'collections' => [ProductCollection::class],
    'brands' => [Brand::class],
    'vendors' => [Vendor::class],
]);

it('answers the same questions by id, for a consumer that holds no model', function () {
    $actor = actorInTeam(7);
    $access = new CatalogAccess();

    expect($access->toProduct($actor, 'update', Product::factory()->ownedBy(7)->create()->id))->toBeTrue()
        ->and($access->toProduct($actor, 'update', Product::factory()->ownedBy(8)->create()->id))->toBeFalse()
        ->and($access->toProduct($actor, 'create'))->toBeTrue()
        ->and($access->toCategory($actor, 'update', Category::factory()->ownedBy(7)->create()->id))->toBeTrue()
        ->and($access->toCollection($actor, 'update', ProductCollection::factory()->ownedBy(8)->create()->id))->toBeFalse()
        ->and($access->toBrand($actor, 'view', Brand::factory()->ownedBy(7)->create()->id))->toBeTrue()
        ->and($access->toVendor($actor, 'view', Vendor::factory()->ownedBy(8)->create()->id))->toBeFalse();
});

it('denies an id that is not there rather than reporting it', function () {
    // "You may not see it" and "it is not there" are the same answer to anyone
    // not entitled to it. Distinguishing them leaks which ids exist.
    $access = new CatalogAccess();
    $actor = actorInTeam(7);

    expect($access->toProduct($actor, 'view', 999999))->toBeFalse()
        ->and($access->toCategory($actor, 'view', 999999))->toBeFalse()
        ->and($access->toCollection($actor, 'view', 999999))->toBeFalse()
        ->and($access->toBrand($actor, 'view', 999999))->toBeFalse()
        ->and($access->toVendor($actor, 'view', 999999))->toBeFalse();
});

it('denies the class-level questions to an actor with no team, by id too', function () {
    $access = new CatalogAccess();
    $actor = actorInTeam(null);

    expect($access->toProduct($actor, 'create'))->toBeFalse()
        ->and($access->toCollection($actor, 'viewAny'))->toBeFalse();
});
