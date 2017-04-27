<?php

namespace Personnage\SimpleCache\Tests\Traits;

trait RedisStore
{
    protected function createStore()
    {
        $client = new \Redis();
        $client->connect(getenv('REDIS_HOST') ?: '127.0.0.1', 6379);

        return new \Personnage\SimpleCache\Redis($client, 'simple-prefix');
    }
}
