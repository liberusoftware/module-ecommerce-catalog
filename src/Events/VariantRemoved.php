<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ids only, because by the time a listener runs the row is gone.
 */
final class VariantRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly int $productId,
        public readonly int $variantId,
        public readonly ?string $sku,
    ) {}
}
