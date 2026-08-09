<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\Catalog\Database\Factories\BrandFactory;

/**
 * Whose product this is, as the shopper understands it.
 *
 * Separate from vendor because they answer different questions and are
 * routinely different answers: a shopper filters by brand, and a buyer chases
 * the vendor. One field spelled "manufacturer" forces a marketplace seller to
 * pick which of the two to lose.
 *
 * Table carries the module prefix — this is a table the package invents rather
 * than one it inherited.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $logo
 * @property string|null $website
 * @property-read Collection<int, Product> $products
 */
class Brand extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_catalog_brands';

    protected $fillable = ['team_id', 'name', 'slug', 'description', 'logo', 'website'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(config('catalog.team_model'));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function newFactory(): Factory
    {
        return BrandFactory::new();
    }
}
