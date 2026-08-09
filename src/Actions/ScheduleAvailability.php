<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use DateTimeInterface;
use Liberu\Ecommerce\Catalog\Events\ProductAvailabilityScheduled;
use Liberu\Ecommerce\Catalog\Exceptions\InvalidAvailabilityWindow;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * The window a product is offered in.
 *
 * Both ends optional and both settable to null, so clearing a window is the
 * same call as setting one — a separate `clearAvailability` would be a second
 * place for the validation to not happen.
 *
 * A window entirely in the past is accepted. It is how a campaign is recorded
 * after the fact, and refusing it would only teach operators to enter a lie.
 */
final class ScheduleAvailability
{
    public function handle(Product $product, ?DateTimeInterface $from, ?DateTimeInterface $until): Product
    {
        if ($from !== null && $until !== null && $until <= $from) {
            throw InvalidAvailabilityWindow::endsBeforeItStarts(
                $from->format(DateTimeInterface::ATOM),
                $until->format(DateTimeInterface::ATOM),
            );
        }

        $product->forceFill([
            'available_from' => $from,
            'available_until' => $until,
        ])->save();

        ProductAvailabilityScheduled::dispatch($product, $from, $until);

        return $product;
    }
}
