<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\Catalog\Database\Factories\ProductVariantFactory;

/**
 * One buyable configuration of a product.
 *
 * Carries what distinguishes it and what ships it — the option values, the
 * codes, the weight. Not what it costs and not how many there are: those are
 * Pricing's and Inventory Ledger's, keyed on this row's id and on
 * `products.id`.
 *
 * @property int $id
 * @property int $product_id
 * @property string|null $sku
 * @property string|null $title
 * @property string|null $option1
 * @property string|null $option2
 * @property string|null $option3
 * @property string|null $barcode
 * @property string|null $weight
 * @property string $weight_unit
 * @property bool $requires_shipping
 * @property int $position
 * @property-read Product $product
 */
class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'sku', 'title', 'option1', 'option2', 'option3',
        'barcode', 'weight', 'weight_unit', 'requires_shipping', 'position',
    ];

    protected $attributes = [
        'weight_unit' => 'kg',
        'requires_shipping' => true,
        'position' => 1,
    ];

    protected $casts = [
        'requires_shipping' => 'boolean',
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The option values in axis order, with the unused axes dropped.
     *
     * A two-axis product leaves `option3` null, and a caller joining all three
     * blindly renders "Red / Large / ".
     *
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_values(array_filter(
            [$this->option1, $this->option2, $this->option3],
            fn (?string $value): bool => $value !== null && $value !== '',
        ));
    }

    protected static function newFactory(): Factory
    {
        return ProductVariantFactory::new();
    }
}
