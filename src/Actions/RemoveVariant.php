<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\VariantRemoved;
use Liberu\Ecommerce\Catalog\Models\ProductVariant;

/**
 * Delete a variant outright.
 *
 * Hard delete, unlike a product. A variant is a configuration rather than a
 * record — orders name what was bought in their own lines, and a soft-deleted
 * variant would keep holding its SKU against the unique index forever, which is
 * the thing merchants actually complain about.
 */
final class RemoveVariant
{
    public function handle(ProductVariant $variant): void
    {
        $productId = (int) $variant->product_id;
        $variantId = (int) $variant->id;
        $sku = $variant->sku;

        $variant->delete();

        VariantRemoved::dispatch($productId, $variantId, $sku);
    }
}
