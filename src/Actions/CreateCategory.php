<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\CategoryCreated;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Support\Slug;

/**
 * A node in the merchant's tree.
 *
 * The slug is unique across the whole tree rather than within a parent, because
 * a category's URL is its slug: two "Accessories" under different parents
 * resolving to one route is the failure this prevents.
 */
final class CreateCategory
{
    public function handle(string $name, ?int $parentId = null, ?int $teamId = null): Category
    {
        $category = Category::query()->create([
            'name' => $name,
            'slug' => Slug::unique(Category::class, $name, 'category'),
            'parent_category_id' => $parentId,
            'team_id' => $teamId,
            'position' => (int) Category::query()->where('parent_category_id', $parentId)->max('position') + 1,
        ]);

        CategoryCreated::dispatch($category);

        return $category;
    }
}
