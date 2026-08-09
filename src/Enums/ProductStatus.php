<?php

namespace Liberu\Ecommerce\Catalog\Enums;

/**
 * A product's lifecycle, and the only transitions that exist.
 *
 * The transitions are data rather than a pile of `if`s so that the domain
 * action and any presentation package asking *what can I offer this operator*
 * read the same source. A surface keeping its own list drifts, and the drift
 * shows up as a button that 500s.
 *
 * Kept separate from visibility on purpose. "Not for sale any more" and "not
 * shown in listings" are different facts with different consequences, and a
 * single field forces one to be spelled as the other.
 */
enum ProductStatus: string
{
    /** Being entered. Never offered, whatever its visibility or publication says. */
    case Draft = 'draft';

    /** Offered, subject to visibility, its effective dates and its publications. */
    case Active = 'active';

    /** No longer sold, but kept whole — orders, reviews and reports still point at it. */
    case Discontinued = 'discontinued';

    /** Out of the catalogue for good. Terminal. */
    case Archived = 'archived';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Archived],
            // Back to draft is deliberately absent: a product that has been
            // offered has been seen, linked and possibly ordered, and pretending
            // it was never finished is how a live URL starts 404ing.
            self::Active => [self::Discontinued, self::Archived],
            self::Discontinued => [self::Active, self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Whether a product in this state can be offered at all. */
    public function isSellable(): bool
    {
        return $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
