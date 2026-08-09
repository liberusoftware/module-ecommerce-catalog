<?php

namespace Liberu\Ecommerce\Catalog\Data;

use JsonSerializable;
use Liberu\Ecommerce\Catalog\Models\ProductVariant;

/**
 * A variant as anything outside this module sees it.
 *
 * No price and no stock, because this module holds neither. A consumer wanting
 * them asks Pricing and Inventory Ledger, keyed on `id`.
 */
final readonly class VariantData implements JsonSerializable
{
    /** @param list<string> $optionValues */
    public function __construct(
        public int $id,
        public int $productId,
        public ?string $sku,
        public ?string $title,
        public array $optionValues,
        public ?string $barcode,
        public ?string $weight,
        public string $weightUnit,
        public bool $requiresShipping,
        public int $position,
    ) {}

    public static function from(ProductVariant $variant): self
    {
        return new self(
            id: (int) $variant->id,
            productId: (int) $variant->product_id,
            sku: $variant->sku,
            title: $variant->title,
            optionValues: $variant->optionValues(),
            barcode: $variant->barcode,
            weight: $variant->weight,
            weightUnit: $variant->weight_unit,
            requiresShipping: (bool) $variant->requires_shipping,
            position: (int) $variant->position,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'sku' => $this->sku,
            'title' => $this->title,
            'option_values' => $this->optionValues,
            'barcode' => $this->barcode,
            'weight' => $this->weight,
            'weight_unit' => $this->weightUnit,
            'requires_shipping' => $this->requiresShipping,
            'position' => $this->position,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
