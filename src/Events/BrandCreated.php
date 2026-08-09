<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\Brand;

final class BrandCreated
{
    use Dispatchable;

    public function __construct(public readonly Brand $brand) {}
}
