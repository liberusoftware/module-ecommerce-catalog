<?php

namespace Liberu\Ecommerce\Catalog\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Liberu\Ecommerce\Catalog\Data\CollectionData;
use Liberu\Ecommerce\Catalog\Data\ProductData;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;

final class CollectionQuery
{
    /**
     * @return LengthAwarePaginator<int, CollectionData>
     */
    public function paginate(?int $teamId, int $perPage = 25): LengthAwarePaginator
    {
        return ProductCollection::query()
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($perPage)
            ->through(CollectionData::from(...));
    }

    public function findBySlug(string $slug): ?CollectionData
    {
        $collection = ProductCollection::query()->withCount('products')->where('slug', $slug)->first();

        return $collection === null ? null : CollectionData::from($collection);
    }

    /**
     * A collection's products in merchandised order, filtered to what the
     * shopper on this channel may see.
     *
     * @return list<ProductData>
     */
    public function products(int $collectionId, ?int $channelId = null): array
    {
        $products = Product::query()
            ->with(['category', 'brand', 'vendor', 'variants', 'tags', 'publications'])
            ->join('collection_items', 'collection_items.product_id', '=', 'products.id')
            ->where('collection_items.collection_id', $collectionId)
            ->listedOn($channelId)
            ->orderBy('collection_items.position')
            ->select('products.*')
            ->get();

        return array_values(array_map(ProductData::from(...), $products->all()));
    }
}
