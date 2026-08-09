<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Liberu\Ecommerce\Catalog\Database\Factories\ProductFactory;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Enums\Visibility;

/**
 * The thing being sold, whole.
 *
 * This is a large model and it stays large. The alternative — splitting the
 * merchandising fields off the identity fields off the SEO fields — buys three
 * models that are always loaded together, always saved together, and can never
 * disagree, which is the definition of one thing. What has been kept out is
 * everything that is genuinely somebody else's rule: price, stock, reviews,
 * images. Pricing and Inventory Ledger extend a product through their own
 * tables keyed on `products.id`.
 *
 * The team model is resolved from configuration at call time and never
 * imported. `store_id` carries no relation at all: stores belong to Commerce
 * Core, which is not a dependency of this package.
 *
 * @property int $id
 * @property int|null $team_id
 * @property int|null $store_id
 * @property int|null $category_id
 * @property int|null $brand_id
 * @property int|null $vendor_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $short_description
 * @property string|null $long_description
 * @property string|null $featured_image
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property ProductStatus $status
 * @property Visibility $visibility
 * @property CarbonImmutable|null $available_from
 * @property CarbonImmutable|null $available_until
 * @property bool $is_featured
 * @property int $position
 * @property CarbonImmutable|null $deleted_at
 * @property-read Category|null $category
 * @property-read Brand|null $brand
 * @property-read Vendor|null $vendor
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, ProductOption> $options
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, ProductPublication> $publications
 */
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'team_id', 'store_id', 'category_id', 'brand_id', 'vendor_id',
        'name', 'slug', 'description', 'short_description', 'long_description',
        'featured_image', 'meta_title', 'meta_description', 'meta_keywords',
        'status', 'visibility', 'available_from', 'available_until',
        'is_featured', 'position',
    ];

    /*
     * Restated here as well as in the migration. `create()` does not read a
     * column default back, so a model built through Eloquent holds null for
     * anything whose default lives only in the schema — and a null `status`
     * cast to an enum is a fatal, not a fallback.
     */
    protected $attributes = [
        'status' => 'draft',
        'visibility' => 'hidden',
        'is_featured' => false,
        'position' => 0,
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'visibility' => Visibility::class,
        'available_from' => 'immutable_datetime',
        'available_until' => 'immutable_datetime',
        'is_featured' => 'boolean',
        'position' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(config('catalog.team_model'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(ProductPublication::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag')->withTimestamps();
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_items', 'product_id', 'collection_id')
            ->withPivot('position')
            ->withTimestamps();
    }

    /**
     * Whether this product is offered on a channel at a moment in time.
     *
     * Delegates to the scope rather than re-deciding in PHP. The rule is four
     * clauses across two tables and it is asked in both a list query and a
     * single-record check; two implementations of it would agree right up until
     * one of them was edited.
     *
     * ponytail: one query per call, so a loop over a page of products is a
     * query per product. Callers rendering a list should filter with
     * `->availableOn()` instead, which is the same rule in one statement.
     */
    public function isAvailableOn(?int $channelId = null, ?DateTimeInterface $at = null): bool
    {
        return static::query()->whereKey($this->getKey())->availableOn($channelId, $at)->exists();
    }

    /** Whether it also belongs in listings, search and feeds. */
    public function isListedOn(?int $channelId = null, ?DateTimeInterface $at = null): bool
    {
        return static::query()->whereKey($this->getKey())->listedOn($channelId, $at)->exists();
    }

    /**
     * Sellable, inside its own effective dates, and — when a channel is named —
     * published there with that publication also in force.
     *
     * Passing no channel asks the catalogue-wide question, which is what an
     * admin listing and a stock report want. Passing one asks the storefront's.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAvailableOn(Builder $query, ?int $channelId = null, ?DateTimeInterface $at = null): void
    {
        $at ??= now();

        $query->where('status', ProductStatus::Active)
            ->where(fn (Builder $window) => $window->whereNull('available_from')->orWhere('available_from', '<=', $at))
            ->where(fn (Builder $window) => $window->whereNull('available_until')->orWhere('available_until', '>', $at))
            ->when($channelId !== null, fn (Builder $scoped) => $scoped->whereHas(
                'publications',
                fn (Builder $publication) => $publication->where('channel_id', $channelId)->live($at),
            ));
    }

    /** @param  Builder<self>  $query */
    public function scopeListedOn(Builder $query, ?int $channelId = null, ?DateTimeInterface $at = null): void
    {
        $query->availableOn($channelId, $at)->where('visibility', Visibility::Public);
    }

    /**
     * The tenancy grain a shopper sees.
     *
     * Null is guarded explicitly rather than passed through: `where('store_id',
     * null)` compiles to `is null`, so scoping to "no store" would list exactly
     * the unassigned rows instead of nothing, which is the opposite of a scope.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForStore(Builder $query, ?int $storeId): void
    {
        $query->when($storeId !== null, fn (Builder $scoped) => $scoped->where('store_id', $storeId));
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }
}
