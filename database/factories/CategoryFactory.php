<?php

namespace Liberu\Ecommerce\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Liberu\Ecommerce\Catalog\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'team_id' => null,
            'parent_category_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 1000000),
        ];
    }

    public function under(Category $parent): static
    {
        return $this->state(fn () => ['parent_category_id' => $parent->id]);
    }

    public function ownedBy(int $teamId): static
    {
        return $this->state(fn () => ['team_id' => $teamId]);
    }
}
