<?php

namespace Personnage\SimpleCache\Tests;

use PHPUnit\Framework\TestCase;
use Personnage\SimpleCache\Cache;
use Personnage\SimpleCache\Redis;
use Personnage\SimpleCache\Memcached;

class CacheTest extends TestCase
{
    public function setUp()
    {
        Cache::removeAllStores();
    }

    public function storeProvider()
    {
        $redis = new Redis(new \Predis\Client(['host' => getenv('REDIS_HOST') ?: '127.0.0.1', 'port' => 6379]));
        $memcached = new Memcached(new \Memcached());

        return [
            [$redis, $memcached],
        ];
    }

    /**
     * @dataProvider storeProvider
     */
    public function testHasStore($redis, $memcached)
    {
        $this->assertFalse(Cache::hasStore($redis));
        $this->assertFalse(Cache::hasStore($memcached));

        Cache::addStore($redis);
        Cache::addStore($memcached, 'cache');

        $this->assertTrue(Cache::hasStore($redis));
        $this->assertTrue(Cache::hasStore('redis'));

        $this->assertTrue(Cache::hasStore($memcached));
        $this->assertTrue(Cache::hasStore('cache'));
    }

    /**
     * @dataProvider storeProvider
     */
    public function testHasDefaultStore($redis, $memcached)
    {
        $this->assertFalse(Cache::hasDefaultStore());

        Cache::addStore($redis, 'default');

        $this->assertTrue(Cache::hasDefaultStore());
        $this->assertTrue(Cache::hasStore('default'));
    }

    /**
     * @dataProvider storeProvider
     */
    public function testGetDefaultStore($redis, $memcached)
    {
        Cache::addStore($redis, 'default');

        $result = Cache::has('key 1');
        $this->assertFalse($result);
        $result = Cache::set('key 1', 'value 1');
        $this->assertTrue($result);
        $result = Cache::get('key 1');
        $this->assertEquals('value 1', $result);

        $result = Cache::store('default')->has('key 3');
        $this->assertFalse($result);
        $result = Cache::store('default')->set('key 3', 'value 3');
        $this->assertTrue($result);
        $result = Cache::store('default')->get('key 3');
        $this->assertEquals('value 3', $result);
    }
}
