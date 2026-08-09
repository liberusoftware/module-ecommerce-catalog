<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\CategoryMoved;
use Liberu\Ecommerce\Catalog\Exceptions\CategoryCycle;
use Liberu\Ecommerce\Catalog\Models\Category;

/**
 * Re-parent a category, or promote it to a root.
 *
 * The cycle guard is the reason this is an action and not a `save()`. A
 * category moved under its own descendant leaves a ring with no root, and
 * nothing that walks the tree — breadcrumbs, menus, descendant queries — ever
 * terminates again. The failure is not a wrong answer, it is a request that
 * never returns, which is why it is refused at the only place a parent changes.
 */
final class MoveCategory
{
    public function handle(Category $category, ?int $parentId): Category
    {
        $from = $category->parent_category_id === null ? null : (int) $category->parent_category_id;

        if ($from === $parentId) {
            return $category;
        }

        if ($parentId !== null && in_array($parentId, $category->descendantIds(), true)) {
            throw CategoryCycle::under((int) $category->id, $parentId);
        }

        $category->forceFill(['parent_category_id' => $parentId])->save();

        CategoryMoved::dispatch($category, $from, $parentId);

        return $category;
    }
}
