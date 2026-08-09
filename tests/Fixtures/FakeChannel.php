<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Catalog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Stands in for whatever the host calls a channel.
 *
 * The point of the fixture is that this package has never seen the real class:
 * `catalog.channel_model` is resolved at call time, so anything with an id
 * works, and Commerce Core is not a dependency of the test suite any more than
 * it is of the package.
 *
 * @property int $id
 * @property string $name
 */
class FakeChannel extends Model
{
    protected $table = 'fake_channels';

    protected $fillable = ['name'];
}
