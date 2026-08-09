<?php

namespace Liberu\Ecommerce\Catalog\Data;

use JsonSerializable;
use Liberu\Ecommerce\Catalog\Models\Vendor;

/**
 * A vendor as anything outside this module is allowed to see it.
 *
 * The contact details are deliberately absent. A vendor's email is a business
 * contact, not catalogue data, and this read model is what a public storefront
 * serialises.
 */
final readonly class VendorData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}

    public static function from(Vendor $vendor): self
    {
        return new self(
            id: (int) $vendor->id,
            name: $vendor->name,
            slug: $vendor->slug,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
