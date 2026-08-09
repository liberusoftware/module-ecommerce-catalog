<?php

namespace Liberu\Ecommerce\Catalog\Telemetry;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Liberu\Ecommerce\Catalog\Events\BrandCreated;
use Liberu\Ecommerce\Catalog\Events\CategoryCreated;
use Liberu\Ecommerce\Catalog\Events\CategoryMoved;
use Liberu\Ecommerce\Catalog\Events\CollectionCreated;
use Liberu\Ecommerce\Catalog\Events\ProductAddedToCollection;
use Liberu\Ecommerce\Catalog\Events\ProductAvailabilityScheduled;
use Liberu\Ecommerce\Catalog\Events\ProductCreated;
use Liberu\Ecommerce\Catalog\Events\ProductOptionSet;
use Liberu\Ecommerce\Catalog\Events\ProductPublished;
use Liberu\Ecommerce\Catalog\Events\ProductRemovedFromCollection;
use Liberu\Ecommerce\Catalog\Events\ProductStatusChanged;
use Liberu\Ecommerce\Catalog\Events\ProductTagsChanged;
use Liberu\Ecommerce\Catalog\Events\ProductUnpublished;
use Liberu\Ecommerce\Catalog\Events\ProductVisibilityChanged;
use Liberu\Ecommerce\Catalog\Events\VariantAdded;
use Liberu\Ecommerce\Catalog\Events\VariantRemoved;
use Liberu\Ecommerce\Catalog\Events\VendorCreated;

/**
 * The module's telemetry: its own domain events, written as structured records.
 *
 * Deliberately a *listener* and not an instrumentation layer. The module
 * consumes observability from the shared foundation rather than duplicating it,
 * so there is no metrics client here, no tracer, and no second logging stack.
 * What it adds is the one thing a foundation cannot supply: the vocabulary. An
 * application's log has no idea that a product leaving `active` took a page off
 * a storefront, or that an unpublish is the reason traffic to a URL stopped.
 *
 * **Off by default.** A catalogue import writes thousands of these in a minute,
 * and a package that starts filling a deployment's log the moment it installs
 * is a package that decided somebody else's retention bill.
 *
 * Levels carry meaning so an alert needs no message parsing: anything that
 * takes a product away from shoppers is a `warning`, and everything else is
 * `info`.
 */
final class DomainEventLogger
{
    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ProductCreated::class => 'onProductCreated',
            ProductStatusChanged::class => 'onProductStatusChanged',
            ProductVisibilityChanged::class => 'onProductVisibilityChanged',
            ProductAvailabilityScheduled::class => 'onProductAvailabilityScheduled',
            ProductPublished::class => 'onProductPublished',
            ProductUnpublished::class => 'onProductUnpublished',
            VariantAdded::class => 'onVariantAdded',
            VariantRemoved::class => 'onVariantRemoved',
            ProductOptionSet::class => 'onProductOptionSet',
            ProductTagsChanged::class => 'onProductTagsChanged',
            ProductAddedToCollection::class => 'onProductAddedToCollection',
            ProductRemovedFromCollection::class => 'onProductRemovedFromCollection',
            CategoryCreated::class => 'onCategoryCreated',
            CategoryMoved::class => 'onCategoryMoved',
            CollectionCreated::class => 'onCollectionCreated',
            BrandCreated::class => 'onBrandCreated',
            VendorCreated::class => 'onVendorCreated',
        ];
    }

    public function onProductCreated(ProductCreated $event): void
    {
        $this->record('product.created', [
            'product_id' => $event->product->id,
            'team_id' => $event->product->team_id,
            'store_id' => $event->product->store_id,
            'slug' => $event->product->slug,
        ]);
    }

    /**
     * A product leaving `active` stops being sellable everywhere at once. That
     * is an operational event somebody should be able to alert on without
     * parsing a message string.
     */
    public function onProductStatusChanged(ProductStatusChanged $event): void
    {
        $this->record('product.status_changed', [
            'product_id' => $event->product->id,
            'from' => $event->from->value,
            'to' => $event->to->value,
        ], $event->from->isSellable() && ! $event->to->isSellable() ? 'warning' : 'info');
    }

    public function onProductVisibilityChanged(ProductVisibilityChanged $event): void
    {
        $this->record('product.visibility_changed', [
            'product_id' => $event->product->id,
            'from' => $event->from->value,
            'to' => $event->to->value,
        ], $event->from->isListed() && ! $event->to->isListed() ? 'warning' : 'info');
    }

    public function onProductAvailabilityScheduled(ProductAvailabilityScheduled $event): void
    {
        $this->record('product.availability_scheduled', [
            'product_id' => $event->product->id,
            'from' => $event->from?->format(DATE_ATOM),
            'until' => $event->until?->format(DATE_ATOM),
        ]);
    }

    public function onProductPublished(ProductPublished $event): void
    {
        $this->record('product.published', [
            'product_id' => $event->publication->product_id,
            'channel_id' => $event->publication->channel_id,
            'published_at' => $event->publication->published_at?->toIso8601String(),
            'unpublished_at' => $event->publication->unpublished_at?->toIso8601String(),
        ]);
    }

    /**
     * The record somebody reaches for when traffic to a URL stops.
     */
    public function onProductUnpublished(ProductUnpublished $event): void
    {
        $this->record('product.unpublished', [
            'product_id' => $event->product->id,
            'channel_id' => $event->channelId,
        ], 'warning');
    }

    public function onVariantAdded(VariantAdded $event): void
    {
        $this->record('variant.added', [
            'product_id' => $event->variant->product_id,
            'variant_id' => $event->variant->id,
            'sku' => $event->variant->sku,
        ]);
    }

    public function onVariantRemoved(VariantRemoved $event): void
    {
        $this->record('variant.removed', [
            'product_id' => $event->productId,
            'variant_id' => $event->variantId,
            'sku' => $event->sku,
        ], 'warning');
    }

    public function onProductOptionSet(ProductOptionSet $event): void
    {
        // The count, not the values. An option's choices are merchant content
        // of unbounded length, and a log line is the wrong place to keep a
        // copy of it.
        $this->record('product.option_set', [
            'product_id' => $event->option->product_id,
            'option' => $event->option->name,
            'value_count' => count($event->option->values),
        ]);
    }

    public function onProductTagsChanged(ProductTagsChanged $event): void
    {
        $this->record('product.tags_changed', [
            'product_id' => $event->product->id,
            'attached' => $event->attached,
            'detached' => $event->detached,
        ]);
    }

    public function onProductAddedToCollection(ProductAddedToCollection $event): void
    {
        $this->record('collection.product_added', [
            'collection_id' => $event->collection->id,
            'product_id' => $event->product->id,
        ]);
    }

    public function onProductRemovedFromCollection(ProductRemovedFromCollection $event): void
    {
        $this->record('collection.product_removed', [
            'collection_id' => $event->collection->id,
            'product_id' => $event->product->id,
        ]);
    }

    public function onCategoryCreated(CategoryCreated $event): void
    {
        $this->record('category.created', [
            'category_id' => $event->category->id,
            'parent_id' => $event->category->parent_category_id,
            'slug' => $event->category->slug,
        ]);
    }

    /**
     * Every breadcrumb and canonical URL under the moved node changes with it,
     * so this is the record that explains a search-traffic cliff.
     */
    public function onCategoryMoved(CategoryMoved $event): void
    {
        $this->record('category.moved', [
            'category_id' => $event->category->id,
            'from_parent_id' => $event->fromParentId,
            'to_parent_id' => $event->toParentId,
        ], 'warning');
    }

    public function onCollectionCreated(CollectionCreated $event): void
    {
        $this->record('collection.created', [
            'collection_id' => $event->collection->id,
            'slug' => $event->collection->slug,
        ]);
    }

    public function onBrandCreated(BrandCreated $event): void
    {
        $this->record('brand.created', ['brand_id' => $event->brand->id, 'slug' => $event->brand->slug]);
    }

    public function onVendorCreated(VendorCreated $event): void
    {
        $this->record('vendor.created', ['vendor_id' => $event->vendor->id, 'slug' => $event->vendor->slug]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(string $event, array $context, string $level = 'info'): void
    {
        if (! config('catalog.telemetry.enabled')) {
            return;
        }

        Log::channel(config('catalog.telemetry.channel'))
            ->log($level, 'catalog.'.$event, $context + ['event' => 'catalog.'.$event]);
    }
}
