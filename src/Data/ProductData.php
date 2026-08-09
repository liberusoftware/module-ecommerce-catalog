<?php

namespace Liberu\Ecommerce\Catalog\Data;

use JsonSerializable;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Enums\Visibility;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * A product as anything outside this module is allowed to see it.
 *
 * The read model exists because of a boundary rule with teeth: an `-api`
 * package may not import a `Models\` class at all. Without something like this
 * the adapter has nothing to serialise, and the rule gets waived rather than
 * met. It is also what makes the contract stable — a column rename inside this
 * package is not a breaking change to a consumer that never saw the column.
 *
 * **No price and no stock.** Not an omission: this module owns neither. A
 * consumer composing a product page asks Pricing and Inventory Ledger with the
 * `id` and the variant ids, which is exactly the integration point those
 * modules were told to key on.
 */
final readonly class ProductData implements JsonSerializable
{
    /**
     * @param  list<VariantData>  $variants
     * @param  list<string>  $tags
     * @param  list<PublicationData>  $publications
     */
    public function __construct(
        public int $id,
        public ?int $teamId,
        public ?int $storeId,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $shortDescription,
        public ?string $featuredImage,
        public ProductStatus $status,
        public Visibility $visibility,
        public ?string $availableFrom,
        public ?string $availableUntil,
        public bool $isFeatured,
        public ?CategoryData $category,
        public ?BrandData $brand,
        public ?VendorData $vendor,
        public array $variants,
        public array $tags,
        public array $publications,
    ) {}

    public static function from(Product $product): self
    {
        return new self(
            id: (int) $product->id,
            teamId: $product->team_id === null ? null : (int) $product->team_id,
            storeId: $product->store_id === null ? null : (int) $product->store_id,
            name: $product->name,
            slug: $product->slug,
            description: $product->description,
            shortDescription: $product->short_description,
            featuredImage: $product->featured_image,
            status: $product->status,
            visibility: $product->visibility,
            availableFrom: $product->available_from?->toIso8601String(),
            availableUntil: $product->available_until?->toIso8601String(),
            isFeatured: (bool) $product->is_featured,
            // Relations are read only when the caller loaded them. A read model
            // that lazily fetches is a read model that turns a paginated list
            // into six queries per row, and the queries in this module all eager
            // load what they hand back.
            category: $product->relationLoaded('category') && $product->category !== null
                ? CategoryData::from($product->category)
                : null,
            brand: $product->relationLoaded('brand') && $product->brand !== null
                ? BrandData::from($product->brand)
                : null,
            vendor: $product->relationLoaded('vendor') && $product->vendor !== null
                ? VendorData::from($product->vendor)
                : null,
            variants: $product->relationLoaded('variants')
                ? array_values(array_map(VariantData::from(...), $product->variants->all()))
                : [],
            tags: $product->relationLoaded('tags')
                ? array_values(array_map(fn ($tag): string => (string) $tag->slug, $product->tags->all()))
                : [],
            publications: $product->relationLoaded('publications')
                ? array_values(array_map(PublicationData::from(...), $product->publications->all()))
                : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->teamId,
            'store_id' => $this->storeId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->shortDescription,
            'featured_image' => $this->featuredImage,
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'available_from' => $this->availableFrom,
            'available_until' => $this->availableUntil,
            'is_featured' => $this->isFeatured,
            'category' => $this->category?->toArray(),
            'brand' => $this->brand?->toArray(),
            'vendor' => $this->vendor?->toArray(),
            'variants' => array_map(fn (VariantData $variant): array => $variant->toArray(), $this->variants),
            'tags' => $this->tags,
            'publications' => array_map(fn (PublicationData $publication): array => $publication->toArray(), $this->publications),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
