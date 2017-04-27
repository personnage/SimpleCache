<?php

namespace Personnage\SimpleCache\Tests\Traits;

trait MemcachedStore
{
    protected function createStore()
    {
        $client = new \Memcached();
        $client->addServer(getenv('MEMCACHED_HOST') ?: '127.0.0.1', 11211);

        return new \Personnage\SimpleCache\Memcached($client, 'simple-prefix');
    }
}
