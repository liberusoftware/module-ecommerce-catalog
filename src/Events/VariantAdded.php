<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\ProductVariant;

/**
 * The event Pricing and Inventory Ledger key on: a new sellable id exists, and
 * neither of them has a row for it yet.
 */
final class VariantAdded
{
    use Dispatchable;

    public function __construct(public readonly ProductVariant $variant) {}
}
