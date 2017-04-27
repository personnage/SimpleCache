<?php

namespace Personnage\SimpleCache\Tests;

use Personnage\SimpleCache\Tests\Traits\RedisStore;

final class RedisStoreTest extends StoreTestCase
{
    use RedisStore;
}
