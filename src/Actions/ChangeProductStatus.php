<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Events\ProductStatusChanged;
use Liberu\Ecommerce\Catalog\Exceptions\InvalidStatusTransition;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * Every lifecycle move a product makes, in one place.
 *
 * One action rather than activate/discontinue/archive: the rule being enforced
 * is the transition table, and three actions means three chances to consult a
 * different copy of it.
 */
final class ChangeProductStatus
{
    public function handle(Product $product, ProductStatus $to): Product
    {
        $from = $product->status;

        // Idempotent rather than an error. A retried import row, a
        // double-clicked button and a redelivered webhook all arrive here
        // asking for a state the product is already in, and none is a fault.
        if ($from === $to) {
            return $product;
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransition::between('product', $from->value, $to->value);
        }

        $product->forceFill(['status' => $to])->save();

        ProductStatusChanged::dispatch($product, $from, $to);

        return $product;
    }
}
