<?php

namespace Liberu\Ecommerce\Catalog\Exceptions;

use DomainException;

/**
 * A window that closes before it opens.
 *
 * Rejected rather than normalised. Silently swapping the ends would mean a
 * mistyped year publishes something for a decade, and clamping them would mean a
 * campaign that never runs and never says why.
 */
final class InvalidAvailabilityWindow extends DomainException
{
    public static function endsBeforeItStarts(string $from, string $until): self
    {
        return new self("An availability window cannot end at [{$until}], before it starts at [{$from}].");
    }
}
