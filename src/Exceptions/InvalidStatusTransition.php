<?php

namespace Liberu\Ecommerce\Catalog\Exceptions;

use DomainException;

/**
 * A lifecycle transition the enum does not admit.
 *
 * A domain exception rather than a validation failure: the presentation layer
 * should never have offered the transition, so this arriving means a surface
 * asked for something its own state machine said was impossible — a bug, not
 * bad input.
 */
final class InvalidStatusTransition extends DomainException
{
    public static function between(string $subject, string $from, string $to): self
    {
        return new self("A {$subject} cannot move from [{$from}] to [{$to}].");
    }
}
