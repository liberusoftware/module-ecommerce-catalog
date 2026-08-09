<?php

namespace Liberu\Ecommerce\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Enums\Visibility;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'team_id' => null,
            'store_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 1000000),
            'description' => $this->faker->sentence(),
            // Active and public, unlike the action's defaults. A test that
            // wanted a draft says so; the rest want a product that behaves like
            // a real one, and making every test promote it first is friction
            // with no reader.
            'status' => ProductStatus::Active,
            'visibility' => Visibility::Public,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Draft]);
    }

    public function discontinued(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Discontinued]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Archived]);
    }

    public function unlisted(): static
    {
        return $this->state(fn () => ['visibility' => Visibility::Unlisted]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['visibility' => Visibility::Hidden]);
    }

    public function ownedBy(int $teamId): static
    {
        return $this->state(fn () => ['team_id' => $teamId]);
    }

    public function inStore(int $storeId): static
    {
        return $this->state(fn () => ['store_id' => $storeId]);
    }

    public function available(?string $from, ?string $until): static
    {
        return $this->state(fn () => ['available_from' => $from, 'available_until' => $until]);
    }
}
