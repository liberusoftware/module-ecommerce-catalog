<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Enums\Visibility;
use Liberu\Ecommerce\Catalog\Models\Product;

final class ProductVisibilityChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly Visibility $from,
        public readonly Visibility $to,
    ) {}
}
