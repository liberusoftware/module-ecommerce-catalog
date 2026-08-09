<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\ProductOptionSet;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductOption;

/**
 * Declare an axis a product varies along, or replace the choices on one.
 *
 * Keyed on the name, so calling it twice with "Size" edits one option rather
 * than creating a second — which the unique index would refuse anyway, and
 * failing there would make a re-run of an import an error instead of a no-op.
 *
 * Values are de-duplicated and re-indexed. A JSON column with holes in its keys
 * decodes to an object rather than an array, and every consumer then has to
 * handle both shapes.
 */
final class SetProductOption
{
    /**
     * @param  list<string>  $values
     */
    public function handle(Product $product, string $name, array $values, ?int $position = null): ProductOption
    {
        $option = ProductOption::query()->updateOrCreate(
            ['product_id' => $product->id, 'name' => $name],
            [
                'values' => array_values(array_unique($values)),
                'position' => $position ?? (int) $product->options()->max('position') + 1,
            ],
        );

        ProductOptionSet::dispatch($option);

        return $option;
    }
}
