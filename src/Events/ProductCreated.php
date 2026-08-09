<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * Past tense, and carrying the model rather than an id.
 *
 * A listener inside this module wants the model; one in Pricing or Inventory
 * Ledger wants `$product->id` and is told to key on it rather than to type-hint
 * the class. Carrying the model serves both; carrying only the id serves
 * neither without a query.
 */
final class ProductCreated
{
    use Dispatchable;

    public function __construct(public readonly Product $product) {}
}
