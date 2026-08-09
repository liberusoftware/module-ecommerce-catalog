<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\ProductCreated;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Support\Slug;

/**
 * The one way a product comes into existence.
 *
 * Tenancy is explicit in the signature and everything else arrives in
 * `$attributes`. That split is deliberate: `team_id` and `store_id` decide who
 * may ever touch the row, so they are the two things a caller must not be able
 * to forget behind a spread operator — and the two things no request payload is
 * allowed to supply.
 *
 * Starts in `draft` and `hidden`, whatever the caller passes. A product is
 * entered over several saves and an import runs in one; either way the moment
 * it becomes visible should be a decision somebody made rather than a side
 * effect of the row appearing.
 */
final class CreateProduct
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $name, ?int $teamId = null, ?int $storeId = null, array $attributes = []): Product
    {
        $product = Product::query()->create([
            ...$attributes,
            'name' => $name,
            'slug' => Slug::unique(Product::class, $attributes['slug'] ?? $name, 'product'),
            'team_id' => $teamId,
            'store_id' => $storeId,
        ]);

        ProductCreated::dispatch($product);

        return $product;
    }
}
