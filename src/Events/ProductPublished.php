<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\ProductPublication;

/**
 * A product was given to a channel.
 *
 * The publication carries the window, so a listener warming a storefront cache
 * can tell "live now" from "live at midnight" without asking again.
 */
final class ProductPublished
{
    use Dispatchable;

    public function __construct(public readonly ProductPublication $publication) {}
}
