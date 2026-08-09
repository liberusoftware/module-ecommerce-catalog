<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Liberu\Ecommerce\Catalog\Database\Factories\ProductCollectionFactory;

/**
 * A merchandised grouping — "Summer", "Gifts under 50" — in a chosen order.
 *
 * Overlaps the category tree on purpose and does a different job: a category is
 * where a product *is*, a collection is where a merchant *put* it this month. A
 * product belongs to one category and any number of collections.
 *
 * Named `ProductCollection` rather than `Collection` because a class called
 * `Collection` in a Laravel package is a permanent import collision with
 * `Illuminate\Support\Collection`, and the one that loses is always the one
 * somebody forgot to alias. The table stays `collections`, which is the name it
 * had in the host.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image
 * @property int $position
 * @property-read Collection<int, Product> $products
 */
class ProductCollection extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'collections';

    protected $fillable = ['team_id', 'name', 'slug', 'description', 'image', 'position'];

    protected $attributes = ['position' => 0];

    protected $casts = ['position' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(config('catalog.team_model'));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_items', 'collection_id', 'product_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('collection_items.position');
    }

    protected static function newFactory(): Factory
    {
        return ProductCollectionFactory::new();
    }
}
