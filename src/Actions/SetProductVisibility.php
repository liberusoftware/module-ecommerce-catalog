<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Enums\Visibility;
use Liberu\Ecommerce\Catalog\Events\ProductVisibilityChanged;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * How discoverable a product is, changed on its own.
 *
 * No transition table, because there is nothing a visibility cannot become —
 * unlike status, visibility describes the present rather than a history, and
 * hiding something is always allowed.
 */
final class SetProductVisibility
{
    public function handle(Product $product, Visibility $to): Product
    {
        $from = $product->visibility;

        if ($from === $to) {
            return $product;
        }

        $product->forceFill(['visibility' => $to])->save();

        ProductVisibilityChanged::dispatch($product, $from, $to);

        return $product;
    }
}
