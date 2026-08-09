<?php

namespace Liberu\Ecommerce\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductVariant;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####-???')),
            'title' => $this->faker->word(),
            'option1' => $this->faker->randomElement(['Small', 'Medium', 'Large']),
            'position' => 1,
        ];
    }

    public function of(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }
}
