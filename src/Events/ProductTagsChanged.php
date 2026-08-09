<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * What moved, not what the set now is.
 *
 * A search index wants to reindex on a real change and skip a no-op sync, and
 * it cannot tell the two apart from the final list.
 */
final class ProductTagsChanged
{
    use Dispatchable;

    /**
     * @param  list<string>  $attached
     * @param  list<string>  $detached
     */
    public function __construct(
        public readonly Product $product,
        public readonly array $attached,
        public readonly array $detached,
    ) {}
}
