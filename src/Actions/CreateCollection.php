<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\CollectionCreated;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;
use Liberu\Ecommerce\Catalog\Support\Slug;

final class CreateCollection
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $name, ?int $teamId = null, array $attributes = []): ProductCollection
    {
        $collection = ProductCollection::query()->create([
            ...$attributes,
            'name' => $name,
            'slug' => Slug::unique(ProductCollection::class, $attributes['slug'] ?? $name, 'collection'),
            'team_id' => $teamId,
        ]);

        CollectionCreated::dispatch($collection);

        return $collection;
    }
}
