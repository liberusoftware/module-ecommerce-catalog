<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * A product stopped being carried by a channel.
 *
 * Carries ids rather than the publication: when a publication that had not
 * started yet is withdrawn, the row is deleted outright, so there is nothing
 * left to hand a listener.
 */
final class ProductUnpublished
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly int $channelId,
    ) {}
}
