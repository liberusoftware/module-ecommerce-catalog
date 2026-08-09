<?php

namespace Liberu\Ecommerce\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductOption;

/**
 * @extends Factory<ProductOption>
 */
class ProductOptionFactory extends Factory
{
    protected $model = ProductOption::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->unique()->word(),
            'position' => 1,
            'values' => ['Small', 'Medium', 'Large'],
        ];
    }
}
