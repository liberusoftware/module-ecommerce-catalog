<?php

namespace Liberu\Ecommerce\Catalog\Queries;

use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\Catalog\Data\ProductData;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * The read side of the catalogue, for consumers outside this module.
 *
 * Returns {@see ProductData}, never models, so a presentation package can
 * render a product without importing one — the boundary rule an `-api` adapter
 * is held to, and a sensible discipline for the others.
 *
 * Two entry points on purpose. `paginate()` is the operator's view: everything
 * in the tenant, whatever state it is in. `storefront()` is the shopper's:
 * sellable, in date, published on this channel, and listed. Nothing takes an
 * optional "and also show the hidden ones" flag, because that flag is how a
 * draft ends up on a live storefront.
 */
final class ProductQuery
{
    /**
     * Every product in a store, in whatever state — the operator's list.
     *
     * @return LengthAwarePaginator<int, ProductData>
     */
    public function paginate(?int $storeId, int $perPage = 25): LengthAwarePaginator
    {
        return $this->base()
            ->forStore($storeId)
            ->orderBy('id')
            ->paginate($perPage)
            ->through(ProductData::from(...));
    }

    /**
     * What a shopper on a channel may see, right now or at a stated moment.
     *
     * @return LengthAwarePaginator<int, ProductData>
     */
    public function storefront(?int $storeId, ?int $channelId = null, ?DateTimeInterface $at = null, int $perPage = 25): LengthAwarePaginator
    {
        return $this->base()
            ->forStore($storeId)
            ->listedOn($channelId, $at)
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($perPage)
            ->through(ProductData::from(...));
    }

    public function find(int $id): ?ProductData
    {
        $product = $this->base()->find($id);

        return $product === null ? null : ProductData::from($product);
    }

    public function findBySlug(string $slug): ?ProductData
    {
        $product = $this->base()->where('slug', $slug)->first();

        return $product === null ? null : ProductData::from($product);
    }

    /**
     * A product a shopper reached by URL.
     *
     * Uses `availableOn` rather than `listedOn`: an unlisted product is
     * deliberately reachable by direct link, and answering 404 for one would
     * defeat the only reason that state exists.
     */
    public function findOnChannel(string $slug, ?int $channelId = null, ?DateTimeInterface $at = null): ?ProductData
    {
        $product = $this->base()->where('slug', $slug)->availableOn($channelId, $at)->first();

        return $product === null ? null : ProductData::from($product);
    }

    /**
     * Everything hanging off a category and its descendants.
     *
     * The descendants are included because a shopper clicking "Outerwear"
     * means the coats filed under "Outerwear → Parkas" too, and a query that
     * matched the node alone would show an empty page for every branch node.
     *
     * @return LengthAwarePaginator<int, ProductData>
     */
    public function inCategory(int $categoryId, ?int $channelId = null, int $perPage = 25): LengthAwarePaginator
    {
        $ids = Category::query()->find($categoryId)?->descendantIds() ?? [$categoryId];

        return $this->base()
            ->whereIn('category_id', $ids)
            ->listedOn($channelId)
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($perPage)
            ->through(ProductData::from(...));
    }

    /** @return Builder<Product> */
    private function base(): Builder
    {
        // Eager loading is here rather than at each call site because
        // `ProductData` only reports a relation it was given, so a query that
        // forgets one returns a product with no variants and says nothing.
        return Product::query()->with(['category', 'brand', 'vendor', 'variants', 'tags', 'publications']);
    }
}
