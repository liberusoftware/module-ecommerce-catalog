<?php

namespace Liberu\Ecommerce\Catalog\Data;

use JsonSerializable;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Queries\CategoryQuery;

/**
 * A category, and optionally the ones under it.
 *
 * Children are passed in rather than read off the model, so building a whole
 * tree is one query in {@see CategoryQuery}
 * rather than one per node.
 */
final readonly class CategoryData implements JsonSerializable
{
    /** @param list<self> $children */
    public function __construct(
        public int $id,
        public ?int $parentId,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $image,
        public int $position,
        public array $children = [],
    ) {}

    /** @param list<self> $children */
    public static function from(Category $category, array $children = []): self
    {
        return new self(
            id: (int) $category->id,
            parentId: $category->parent_category_id === null ? null : (int) $category->parent_category_id,
            name: $category->name,
            slug: $category->slug,
            description: $category->description,
            image: $category->image,
            position: (int) $category->position,
            children: $children,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'position' => $this->position,
            'children' => array_map(fn (self $child): array => $child->toArray(), $this->children),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
