<?php

namespace Personnage\SimpleCache\Tests\Traits;

trait MemoryStore
{
    protected function createStore()
    {
        return new \Personnage\SimpleCache\Memory('simple-prefix');
    }
}
