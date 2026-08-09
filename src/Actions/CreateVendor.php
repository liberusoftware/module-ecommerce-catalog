<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\VendorCreated;
use Liberu\Ecommerce\Catalog\Models\Vendor;
use Liberu\Ecommerce\Catalog\Support\Slug;

final class CreateVendor
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $name, ?int $teamId = null, array $attributes = []): Vendor
    {
        $vendor = Vendor::query()->create([
            ...$attributes,
            'name' => $name,
            'slug' => Slug::unique(Vendor::class, $attributes['slug'] ?? $name, 'vendor'),
            'team_id' => $teamId,
        ]);

        VendorCreated::dispatch($vendor);

        return $vendor;
    }
}
