<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Liberu\Ecommerce\Catalog\Events\ProductUnpublished;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductPublication;

/**
 * Take a product off a channel.
 *
 * A publication that is live is closed rather than deleted — the dates it ran
 * for are the answer to "when did this stop being on the site", and that
 * question is asked after the fact, when there is no other record left. One
 * that has not started, or has already ended, is deleted: nothing happened, so
 * there is nothing to keep.
 *
 * Silent when there is no publication at all. "Not on this channel" is the
 * state the caller asked for.
 */
final class UnpublishFromChannel
{
    public function handle(Product $product, int $channelId): void
    {
        $publication = ProductPublication::query()
            ->where('product_id', $product->id)
            ->where('channel_id', $channelId)
            ->first();

        if ($publication === null) {
            return;
        }

        if ($publication->isLive()) {
            $publication->forceFill(['unpublished_at' => now()])->save();
        } else {
            $publication->delete();
        }

        ProductUnpublished::dispatch($product, $channelId);
    }
}
