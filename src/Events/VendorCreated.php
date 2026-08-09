<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\Vendor;

final class VendorCreated
{
    use Dispatchable;

    public function __construct(public readonly Vendor $vendor) {}
}
