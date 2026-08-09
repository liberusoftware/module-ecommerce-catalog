<?php

namespace Liberu\Ecommerce\Catalog\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * The effective dates a product was given.
 *
 * Both ends carried and both nullable: clearing a window is as much a change as
 * setting one, and a listener that only ever sees the new dates cannot tell a
 * campaign being scheduled from one being cancelled.
 */
final class ProductAvailabilityScheduled
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly ?DateTimeInterface $from,
        public readonly ?DateTimeInterface $until,
    ) {}
}
