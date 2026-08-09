<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\Catalog\Database\Factories\ProductOptionFactory;

/**
 * One axis a product varies along — "Size", and the sizes it comes in.
 *
 * The axis is declared here and the combinations live on the variants. Keeping
 * the declaration separate is what lets a surface render the pickers for a
 * product that has no variants entered yet.
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property int $position
 * @property list<string> $values
 * @property-read Product $product
 */
class ProductOption extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'name', 'position', 'values'];

    protected $attributes = ['position' => 1];

    protected $casts = [
        'values' => 'array',
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): Factory
    {
        return ProductOptionFactory::new();
    }
}
