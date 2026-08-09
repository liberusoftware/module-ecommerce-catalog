<?php

namespace Liberu\Ecommerce\Catalog\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\Catalog\Models\Brand;
use Liberu\Ecommerce\Catalog\Models\Category;
use Liberu\Ecommerce\Catalog\Models\Product;
use Liberu\Ecommerce\Catalog\Models\ProductCollection;
use Liberu\Ecommerce\Catalog\Models\Vendor;

/**
 * Authorization by id, for consumers that may not hold a model.
 *
 * The policies are the authority and this changes none of them — it resolves
 * the subject and asks the gate. It exists because the alternative for an
 * adapter forbidden from importing `Models\` is to make its own authorization
 * decision, which is exactly the *business authorization solely in the
 * presentation layer* that the epic excludes.
 *
 * A missing subject is denied rather than reported. "You may not see it" and
 * "it is not there" are the same answer to anyone not entitled to it, and
 * distinguishing them leaks which ids exist.
 */
final class CatalogAccess
{
    public function toProduct(Authenticatable $actor, string $ability, ?int $productId = null): bool
    {
        return $this->check($actor, $ability, Product::class, $productId);
    }

    public function toCategory(Authenticatable $actor, string $ability, ?int $categoryId = null): bool
    {
        return $this->check($actor, $ability, Category::class, $categoryId);
    }

    public function toCollection(Authenticatable $actor, string $ability, ?int $collectionId = null): bool
    {
        return $this->check($actor, $ability, ProductCollection::class, $collectionId);
    }

    public function toBrand(Authenticatable $actor, string $ability, ?int $brandId = null): bool
    {
        return $this->check($actor, $ability, Brand::class, $brandId);
    }

    public function toVendor(Authenticatable $actor, string $ability, ?int $vendorId = null): bool
    {
        return $this->check($actor, $ability, Vendor::class, $vendorId);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function check(Authenticatable $actor, string $ability, string $model, ?int $id): bool
    {
        if ($id === null) {
            return Gate::forUser($actor)->allows($ability, $model);
        }

        $subject = $model::query()->find($id);

        return $subject !== null && Gate::forUser($actor)->allows($ability, $subject);
    }
}
