<?php

namespace Personnage\SimpleCache\Tests\Traits;

trait PredisStore
{
    protected function createStore()
    {
        $client = new \Predis\Client([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => 6379,
        ]);

        return new \Personnage\SimpleCache\Redis($client, 'simple-prefix');
    }
}
