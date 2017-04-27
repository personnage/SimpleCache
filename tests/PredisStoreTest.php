<?php

namespace Personnage\SimpleCache\Tests;

use Personnage\SimpleCache\Tests\Traits\PredisStore;

final class PredisStoreTest extends StoreTestCase
{
    use PredisStore;
}
