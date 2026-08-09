<?php

namespace Liberu\Ecommerce\Catalog\Queries;

use Liberu\Ecommerce\Catalog\Data\CategoryData;
use Liberu\Ecommerce\Catalog\Models\Category;

/**
 * The read side of the category tree.
 *
 * The whole tree is built from one query and assembled in memory. A navigation
 * menu is read on every page of a storefront and is tens of nodes at worst, so
 * the query that matters is the one that runs per request, not the memory the
 * assembly costs.
 */
final class CategoryQuery
{
    /**
     * The tree, roots first, each node carrying its children.
     *
     * @return list<CategoryData>
     */
    public function tree(?int $teamId = null): array
    {
        $categories = Category::query()
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        /** @var array<int|string, list<Category>> $byParent */
        $byParent = [];

        foreach ($categories as $category) {
            $byParent[$category->parent_category_id ?? 'root'][] = $category;
        }

        return $this->build($byParent, 'root');
    }

    public function find(int $id): ?CategoryData
    {
        $category = Category::query()->find($id);

        return $category === null ? null : CategoryData::from($category);
    }

    public function findBySlug(string $slug): ?CategoryData
    {
        $category = Category::query()->where('slug', $slug)->first();

        return $category === null ? null : CategoryData::from($category);
    }

    /**
     * The path from a root down to a category, for a breadcrumb.
     *
     * @return list<CategoryData>
     */
    public function breadcrumb(int $id): array
    {
        $trail = [];
        $category = Category::query()->find($id);

        // Bounded by the number of categories, not by the shape of the tree.
        // `MoveCategory` refuses cycles, but a row edited around it must not be
        // able to hang a page render.
        $guard = Category::query()->count() + 1;

        while ($category !== null && $guard-- > 0) {
            array_unshift($trail, CategoryData::from($category));
            $category = $category->parent_category_id === null ? null : Category::query()->find($category->parent_category_id);
        }

        return $trail;
    }

    /**
     * @param  array<int|string, list<Category>>  $byParent
     * @return list<CategoryData>
     */
    private function build(array $byParent, int|string $key): array
    {
        return array_map(
            fn (Category $category): CategoryData => CategoryData::from(
                $category,
                $this->build($byParent, $category->id),
            ),
            $byParent[$key] ?? [],
        );
    }
}
