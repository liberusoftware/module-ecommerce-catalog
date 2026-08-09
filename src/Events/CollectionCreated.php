<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;

final class CollectionCreated
{
    use Dispatchable;

    public function __construct(public readonly ProductCollection $collection) {}
}
