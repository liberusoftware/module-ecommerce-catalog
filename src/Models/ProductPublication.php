<?php

namespace Liberu\Ecommerce\Catalog\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * A product carried by a channel, for a window of time.
 *
 * `channel_id` is a number and nothing more. Channels belong to
 * `liberusoftware/ecommerce-commerce-core`, which is not on Packagist and is not
 * a dependency of this package, so publication is a column this module owns
 * rather than a relation into somebody else's model. Every rule here is
 * enforceable on the id alone.
 *
 * @property int $id
 * @property int $product_id
 * @property int $channel_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $unpublished_at
 * @property-read Product $product
 */
class ProductPublication extends Model
{
    protected $table = 'ecommerce_catalog_publications';

    protected $fillable = ['product_id', 'channel_id', 'published_at', 'unpublished_at'];

    protected $casts = [
        'published_at' => 'immutable_datetime',
        'unpublished_at' => 'immutable_datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The channel, if the host has told this package where channels live.
     *
     * Opt-in and resolved from config at call time, exactly the way the team
     * model is: a package that names another package's class in a `use`
     * statement has quietly acquired a dependency on it. Nothing in this module
     * calls this — it exists so a panel can render a name instead of a number.
     */
    public function channel(): BelongsTo
    {
        $model = config('catalog.channel_model');

        if (! is_string($model) || $model === '') {
            throw new RuntimeException('No channel model is configured. Set `catalog.channel_model` before loading the `channel` relation.');
        }

        return $this->belongsTo($model, 'channel_id');
    }

    /** Whether this publication is in force at a moment in time. */
    public function isLive(?DateTimeInterface $at = null): bool
    {
        $at ??= now();

        return ($this->published_at === null || $this->published_at <= $at)
            && ($this->unpublished_at === null || $this->unpublished_at > $at);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query, ?DateTimeInterface $at = null): void
    {
        $at ??= now();

        // `whereNull` explicitly on both ends: `where('published_at', null)`
        // compiles to `is null`, which would turn "started already" into
        // "never scheduled" and quietly publish the opposite set.
        $query->where(fn (Builder $window) => $window->whereNull('published_at')->orWhere('published_at', '<=', $at))
            ->where(fn (Builder $window) => $window->whereNull('unpublished_at')->orWhere('unpublished_at', '>', $at));
    }
}
