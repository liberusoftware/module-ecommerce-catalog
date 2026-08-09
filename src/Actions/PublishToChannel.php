<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use DateTimeInterface;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Events\ProductPublished;
use Liberu\Ecommerce\Catalog\Exceptions\InvalidStatusTransition;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductPublication;

/**
 * Give a product to a channel, optionally for a window.
 *
 * `channel_id` is a number this module stores and never resolves. Channels
 * belong to Commerce Core, which is not a dependency here, so there is nothing
 * to look up and nothing to validate against — an id for a channel that does
 * not exist publishes to a storefront that will never ask, which is inert.
 *
 * Publishing a draft is allowed on purpose: a merchant stages a season by
 * publishing everything and then flipping the products live. What is refused is
 * publishing an archived product, because that is somebody trying to resurrect
 * a record through the back door.
 *
 * Re-publishing an existing publication rewrites its window rather than
 * failing, so the same import running twice is not an incident.
 */
final class PublishToChannel
{
    public function handle(Product $product, int $channelId, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null): ProductPublication
    {
        if ($product->status === ProductStatus::Archived) {
            throw InvalidStatusTransition::between('product', ProductStatus::Archived->value, 'published');
        }

        $publication = ProductPublication::query()->updateOrCreate(
            ['product_id' => $product->id, 'channel_id' => $channelId],
            ['published_at' => $from, 'unpublished_at' => $until],
        );

        ProductPublished::dispatch($publication);

        return $publication;
    }
}
