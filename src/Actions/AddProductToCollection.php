<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\ProductAddedToCollection;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;

/**
 * Put a product in a collection, at the end unless told otherwise.
 *
 * `syncWithoutDetaching` rather than `attach`: the unique index means a second
 * attach of the same pair is an integrity error, and adding something that is
 * already there is not a fault a merchant should see a stack trace for.
 */
final class AddProductToCollection
{
    public function handle(ProductCollection $collection, Product $product, ?int $position = null): ProductCollection
    {
        $alreadyIn = $collection->products()->whereKey($product->getKey())->exists();

        $collection->products()->syncWithoutDetaching([
            $product->id => ['position' => $position ?? (int) $collection->products()->max('collection_items.position') + 1],
        ]);

        if (! $alreadyIn) {
            ProductAddedToCollection::dispatch($collection, $product);
        }

        return $collection;
    }
}
