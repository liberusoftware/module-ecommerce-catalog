<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\ProductOption;

final class ProductOptionSet
{
    use Dispatchable;

    public function __construct(public readonly ProductOption $option) {}
}
