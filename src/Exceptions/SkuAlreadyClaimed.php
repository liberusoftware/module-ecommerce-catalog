<?php

namespace Liberu\Ecommerce\Catalog\Exceptions;

use DomainException;

/**
 * A SKU another variant already holds.
 *
 * The database says so too, and deliberately: this exists so the caller gets a
 * sentence rather than an integrity-constraint dump, not so the unique index can
 * be dropped. Two variants sharing a SKU is a mis-ship, and the last defence
 * against it belongs in the schema.
 */
final class SkuAlreadyClaimed extends DomainException
{
    public static function is(string $sku): self
    {
        return new self("The SKU [{$sku}] already belongs to another variant.");
    }
}
