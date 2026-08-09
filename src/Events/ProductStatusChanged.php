<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * Both ends of the move, because "what was it before" is the question an audit
 * actually asks, and a listener deciding whether the product just stopped being
 * sellable cannot answer it from the new value alone.
 */
final class ProductStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly ProductStatus $from,
        public readonly ProductStatus $to,
    ) {}
}
