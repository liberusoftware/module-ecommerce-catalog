<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Liberu\Ecommerce\Catalog\Database\Factories\TagFactory;

/**
 * A free-form label, shared across the catalogue.
 *
 * Deliberately not team-scoped. A tag is a word, and two merchants writing
 * "waterproof" mean the same word; per-team tags turn a filter into a join
 * against a vocabulary nobody curates. What is scoped is the product a tag is
 * attached to, which is the row a policy actually protects.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property-read Collection<int, Product> $products
 */
class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tag')->withTimestamps();
    }

    protected static function newFactory(): Factory
    {
        return TagFactory::new();
    }
}
