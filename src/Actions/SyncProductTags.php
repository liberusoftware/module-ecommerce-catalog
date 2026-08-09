<?php

namespace Liberu\Ecommerce\Catalog\Actions;

use Illuminate\Support\Str;
use Liberu\Ecommerce\Catalog\Events\ProductTagsChanged;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\Tag;

/**
 * Set a product's tags from names, creating the ones that do not exist.
 *
 * Names rather than ids, because that is what every surface that tags things
 * actually has — a free-text field, a CSV column, a bulk edit. Making the
 * caller resolve ids first means each of them writes this lookup, and each of
 * them writes it slightly differently.
 *
 * Matching is on the slug rather than the name, so "Water Resistant", "water
 * resistant" and "Water  Resistant" are one tag. The alternative is a
 * vocabulary that grows a near-duplicate every time somebody types. This is the
 * one place in the module that does *not* uniquify a slug by suffix: here a
 * collision is the point.
 */
final class SyncProductTags
{
    /**
     * @param  list<string>  $names
     */
    public function handle(Product $product, array $names): Product
    {
        $before = $this->slugsOn($product);

        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $tag = Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name) ?: 'tag'],
                ['name' => $name],
            );

            $ids[] = $tag->id;
        }

        $product->tags()->sync($ids);

        $after = $this->slugsOn($product);
        $attached = array_values(array_diff($after, $before));
        $detached = array_values(array_diff($before, $after));

        // Silent when nothing moved. A bulk edit re-saving fifty unchanged
        // products should not make a search index reindex fifty documents.
        if ($attached !== [] || $detached !== []) {
            ProductTagsChanged::dispatch($product, $attached, $detached);
        }

        return $product;
    }

    /** @return list<string> */
    private function slugsOn(Product $product): array
    {
        return array_map(strval(...), $product->tags()->pluck('slug')->all());
    }
}
