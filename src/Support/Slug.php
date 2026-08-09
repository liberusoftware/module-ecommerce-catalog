<?php

namespace Liberu\Ecommerce\Catalog\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A slug that is unique in its table, derived rather than demanded.
 *
 * Made unique by suffix rather than by rejection: the merchant naming a second
 * "Classic Tee" wants a second product, not a validation error about a field
 * they were never shown. One helper rather than the same loop copied into six
 * create actions — and six copies is how one of them ends up without the
 * uniqueness check.
 */
final class Slug
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $value, string $fallback = 'item', string $column = 'slug'): string
    {
        $base = Str::slug($value) ?: $fallback;
        $slug = $base;

        // Global scopes off, so soft-deleted rows count. A trashed row still
        // holds the unique index, and a slug it owns is genuinely taken until
        // somebody purges it — skipping those is how a create fails on a
        // constraint the loop just told the caller was free.
        for ($suffix = 2; $model::query()->withoutGlobalScopes()->where($column, $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
