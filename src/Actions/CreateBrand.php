<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\BrandCreated;
use Liberu\Ecommerce\Catalog\Models\Brand;
use Liberu\Ecommerce\Catalog\Support\Slug;

final class CreateBrand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $name, ?int $teamId = null, array $attributes = []): Brand
    {
        $brand = Brand::query()->create([
            ...$attributes,
            'name' => $name,
            'slug' => Slug::unique(Brand::class, $attributes['slug'] ?? $name, 'brand'),
            'team_id' => $teamId,
        ]);

        BrandCreated::dispatch($brand);

        return $brand;
    }
}
