<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\Catalog\Database\Factories\VendorFactory;

/**
 * Who the merchant gets this from.
 *
 * Thin on purpose. Terms, settlement, purchase orders and lead times belong to
 * whichever module actually transacts with the vendor; what the catalogue needs
 * is an attribution it can group and filter by, and somewhere to look up who to
 * ring.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property string $slug
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property-read Collection<int, Product> $products
 */
class Vendor extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_catalog_vendors';

    protected $fillable = ['team_id', 'name', 'slug', 'contact_email', 'contact_phone'];

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
        return VendorFactory::new();
    }
}
