<?php

namespace Liberu\Ecommerce\Catalog\Exceptions;

use DomainException;

/**
 * A category move that would make the tree point at itself.
 *
 * Worth its own exception because the failure mode is not an error message but
 * an infinite loop: every breadcrumb, menu render and descendant query walks
 * parents until it reaches null, and a cycle means none of them ever does.
 */
final class CategoryCycle extends DomainException
{
    public static function under(int $categoryId, int $parentId): self
    {
        return new self("Category [{$categoryId}] cannot be moved under [{$parentId}], which is itself or one of its descendants.");
    }
}
