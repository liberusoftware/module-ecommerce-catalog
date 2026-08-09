<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\VariantAdded;
use Liberu\Ecommerce\Catalog\Exceptions\SkuAlreadyClaimed;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductVariant;

/**
 * Add one buyable configuration to a product.
 *
 * The SKU is checked here as well as by the unique index, so a caller gets a
 * sentence naming the code instead of an integrity-constraint dump. The index
 * stays: this check is not atomic, and two concurrent imports claiming one SKU
 * must lose at the database rather than both succeed.
 *
 * Position is appended rather than asked for. A caller supplying it is a caller
 * who has to know what is already there, and the common case — building a
 * product top to bottom — then has to count.
 */
final class AddVariant
{
    /**
     * @param  list<string>  $optionValues  In axis order, at most three.
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Product $product, ?string $sku = null, ?string $title = null, array $optionValues = [], array $attributes = []): ProductVariant
    {
        if ($sku !== null && ProductVariant::query()->where('sku', $sku)->exists()) {
            throw SkuAlreadyClaimed::is($sku);
        }

        $variant = $product->variants()->create([
            ...$attributes,
            'sku' => $sku,
            'title' => $title,
            'option1' => $optionValues[0] ?? null,
            'option2' => $optionValues[1] ?? null,
            'option3' => $optionValues[2] ?? null,
            'position' => (int) $product->variants()->max('position') + 1,
        ]);

        VariantAdded::dispatch($variant);

        return $variant;
    }
}
