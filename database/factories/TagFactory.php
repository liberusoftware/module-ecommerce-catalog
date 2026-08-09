<?php

namespace Liberu\Ecommerce\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Liberu\Ecommerce\Catalog\Models\Tag;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 1000000),
        ];
    }
}
