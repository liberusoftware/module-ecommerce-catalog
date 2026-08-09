<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\Catalog\Actions\PublishToChannel;
use Liberu\Ecommerce\Catalog\CatalogServiceProvider;
use Liberu\Ecommerce\Catalog\Models\Brand;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;
use Liberu\Ecommerce\Catalog\Models\Vendor;
use Liberu\Ecommerce\Catalog\Policies\ProductPolicy;
use Liberu\Ecommerce\Catalog\Policies\TaxonomyPolicy;
use Liberu\Ecommerce\Catalog\Tests\Fixtures\FakeChannel;

it('boots nothing on install, and names its provider for the module manager to find', function () {
    // The package ships no `extra.laravel.providers`, so Composer discovery
    // registers nothing; `ModuleManagerServiceProvider` reads `module.json` and
    // registers this only when the deployment names the module.
    $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true);
    $module = json_decode((string) file_get_contents(__DIR__.'/../module.json'), true);

    expect($composer['extra']['laravel']['providers'] ?? [])->toBe([])
        ->and($module['provider'])->toBe(CatalogServiceProvider::class)
        ->and(class_exists($module['provider']))->toBeTrue();
});

it('registers a policy for everything a host will authorize', function (string $model, string $policy) {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with([
    'products' => [Product::class, ProductPolicy::class],
    'categories' => [Category::class, TaxonomyPolicy::class],
    'collections' => [ProductCollection::class, TaxonomyPolicy::class],
    'brands' => [Brand::class, TaxonomyPolicy::class],
    'vendors' => [Vendor::class, TaxonomyPolicy::class],
]);

it('resolves the host’s team model from config rather than importing it', function () {
    // A module that names `App\Models\Team` in a `use` statement installs into
    // exactly one application. This is the alternative, and this test is what
    // keeps it honest.
    config()->set('catalog.team_model', FakeChannel::class);

    expect(Product::factory()->create()->team()->getRelated())->toBeInstanceOf(FakeChannel::class)
        ->and(Category::factory()->create()->team()->getRelated())->toBeInstanceOf(FakeChannel::class)
        ->and(Brand::factory()->create()->team()->getRelated())->toBeInstanceOf(FakeChannel::class)
        ->and(Vendor::factory()->create()->team()->getRelated())->toBeInstanceOf(FakeChannel::class)
        ->and(ProductCollection::factory()->create()->team()->getRelated())->toBeInstanceOf(FakeChannel::class);
});

it('keeps a publication working with no channel model configured at all', function () {
    // The default. Publication is a `channel_id` this module owns, and every
    // rule it enforces works on the number alone.
    expect(config('catalog.channel_model'))->toBeNull();

    $publication = (new PublishToChannel())->handle(Product::factory()->create(), 9);

    expect($publication->channel_id)->toBe(9)
        ->and($publication->isLive())->toBeTrue();
});

it('loads a channel through config when the host says where channels live', function () {
    Schema::create('fake_channels', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    config()->set('catalog.channel_model', FakeChannel::class);
    $channel = FakeChannel::query()->create(['name' => 'Web']);

    $publication = (new PublishToChannel())->handle(Product::factory()->create(), (int) $channel->id);

    expect($publication->channel->name)->toBe('Web');
});

it('refuses to invent a channel model rather than failing somewhere less obvious', function () {
    $publication = (new PublishToChannel())->handle(Product::factory()->create(), 1);

    expect(fn () => $publication->channel())->toThrow(RuntimeException::class, 'catalog.channel_model');
});

it('never mentions the host application’s namespace anywhere in src', function () {
    // The boundary suite asserts this too. It is repeated here because it is
    // the single rule that decides whether this package is reusable at all, and
    // a suite that only fails in somebody else's repository is a suite that
    // gets waived.
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../src', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php'
            && preg_match('/(?:use|new|extends|implements)\s+App\\\\/', (string) file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('publishes its config under a tag a host can name', function () {
    expect(ServiceProvider::pathsToPublish(CatalogServiceProvider::class, 'catalog-config'))->not->toBeEmpty();
});
