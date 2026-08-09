<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\Catalog\Database\Factories\CategoryFactory;

/**
 * The merchant's own tree. A product sits in exactly one node.
 *
 * One category per product, not many: a tree where a thing can be in two places
 * is not a tree, and every breadcrumb then has to pick a branch arbitrarily.
 * The many-to-many need is real and it is what collections and tags are for.
 *
 * Table name kept bare — `product_categories` existed in the host before this
 * package did — and so is the host's `parent_category_id` column, so the
 * extraction is a namespace change rather than a data migration.
 *
 * @property int $id
 * @property int|null $team_id
 * @property int|null $parent_category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image
 * @property int $position
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 * @property-read Collection<int, Product> $products
 */
class Category extends Model
{
    use HasFactory;

    protected $table = 'product_categories';

    protected $fillable = ['team_id', 'parent_category_id', 'name', 'slug', 'description', 'image', 'position'];

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_category_id')->orderBy('position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * This category and everything under it, root first.
     *
     * Walked rather than joined: the tree is a merchant's navigation, tens of
     * nodes deep at the very worst, and a recursive CTE would buy nothing but a
     * dialect problem on SQLite.
     *
     * ponytail: one query per level. If a catalogue ever grows a genuinely deep
     * tree, add a materialised path column and read it in one.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->getKey()];
        $frontier = [$this->getKey()];

        while ($frontier !== []) {
            $frontier = static::query()->whereIn('parent_category_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return array_map(intval(...), $ids);
    }

    /** @param  Builder<self>  $query */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_category_id');
    }

    protected static function newFactory(): Factory
    {
        return CategoryFactory::new();
    }
}
