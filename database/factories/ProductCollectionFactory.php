<?php

namespace Liberu\Ecommerce\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;

/**
 * @extends Factory<ProductCollection>
 */
class ProductCollectionFactory extends Factory
{
    protected $model = ProductCollection::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'team_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 1000000),
        ];
    }

    public function ownedBy(int $teamId): static
    {
        return $this->state(fn () => ['team_id' => $teamId]);
    }
}
