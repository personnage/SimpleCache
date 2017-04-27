<?php

namespace Personnage\SimpleCache\Tests;

use Personnage\SimpleCache\Tests\Traits\MemcachedStore;

final class MemcachedStoreTest extends StoreTestCase
{
    use MemcachedStore;
}
