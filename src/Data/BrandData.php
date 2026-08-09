<?php

namespace Liberu\Ecommerce\Catalog\Data;

use JsonSerializable;
use Liberu\Ecommerce\Catalog\Models\Brand;

/**
 * A brand as anything outside this module is allowed to see it.
 *
 * @see ProductData for why the read models exist at all.
 */
final readonly class BrandData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $logo,
        public ?string $website,
    ) {}

    public static function from(Brand $brand): self
    {
        return new self(
            id: (int) $brand->id,
            name: $brand->name,
            slug: $brand->slug,
            logo: $brand->logo,
            website: $brand->website,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'website' => $this->website,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
