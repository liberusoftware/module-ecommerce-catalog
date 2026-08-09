<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\ProductRemovedFromCollection;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;

/**
 * Take a product out of a collection.
 *
 * Silent when it was not in it — the caller asked for a state, and it holds.
 */
final class RemoveProductFromCollection
{
    public function handle(ProductCollection $collection, Product $product): ProductCollection
    {
        if ($collection->products()->detach($product->id) > 0) {
            ProductRemovedFromCollection::dispatch($collection, $product);
        }

        return $collection;
    }
}
