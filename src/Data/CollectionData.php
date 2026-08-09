<?php

namespace Liberu\Ecommerce\Catalog\Data;

use JsonSerializable;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;

final readonly class CollectionData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public ?int $teamId,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $image,
        public int $position,
        public int $productCount,
    ) {}

    public static function from(ProductCollection $collection): self
    {
        return new self(
            id: (int) $collection->id,
            teamId: $collection->team_id === null ? null : (int) $collection->team_id,
            name: $collection->name,
            slug: $collection->slug,
            description: $collection->description,
            image: $collection->image,
            position: (int) $collection->position,
            // `products_count` when the caller loaded it, and a query when it
            // did not. A read model that silently returns zero because nobody
            // called `withCount` is worse than one that costs a query.
            productCount: (int) ($collection->products_count ?? $collection->products()->count()),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->teamId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'position' => $this->position,
            'product_count' => $this->productCount,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
