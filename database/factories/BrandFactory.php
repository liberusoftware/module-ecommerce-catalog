<?php

namespace Liberu\Ecommerce\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Liberu\Ecommerce\Catalog\Models\Brand;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'team_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 1000000),
        ];
    }

    public function ownedBy(int $teamId): static
    {
        return $this->state(fn () => ['team_id' => $teamId]);
    }
}
