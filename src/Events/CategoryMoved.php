<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\Category;

/**
 * A subtree changed address.
 *
 * Every breadcrumb, menu and canonical URL under the moved node changes with
 * it, which is why the previous parent is carried: a cache keyed on the old
 * path has no other way to find itself.
 */
final class CategoryMoved
{
    use Dispatchable;

    public function __construct(
        public readonly Category $category,
        public readonly ?int $fromParentId,
        public readonly ?int $toParentId,
    ) {}
}
