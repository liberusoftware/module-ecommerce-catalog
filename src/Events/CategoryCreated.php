<?php

namespace Liberu\Ecommerce\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\Catalog\Models\Category;

final class CategoryCreated
{
    use Dispatchable;

    public function __construct(public readonly Category $category) {}
}
