<?php

namespace Personnage\SimpleCache\Tests;

use Cache\IntegrationTests\SimpleCacheTest;
use Personnage\SimpleCache\Event\Emitter;

abstract class StoreTestCase extends SimpleCacheTest
{
    /**
     * @var \Personnage\SimpleCache\Store
     */
    protected $store;

    /**
     * @return \Personnage\SimpleCache\Store
     */
    abstract protected function createStore();

    protected function setUp()
    {
        $this->store = $this->createStore();

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->store->clear();

        parent::tearDown();
    }

    public function createSimpleCache()
    {
        return $this->createStore();
    }

    /**
     * @dataProvider validKeys
     */
    public function testIncrement($key)
    {
        $this->store->set($key, 1);

        $result = $this->store->increment($key);
        $this->assertEquals(2, $result);
        $this->assertEquals(2, $this->store->get($key));

        $result = $this->store->increment($key, 3);
        $this->assertEquals(5, $result);
        $this->assertEquals(5, $this->store->get($key));

        $result = $this->store->increment($key, 0);
        $this->assertEquals(5, $result);
        $this->assertEquals(5, $this->store->get($key));
    }

    public function testIncrementFail()
    {
        $this->assertFalse($this->store->increment('key'));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testIncrementInvalidKeys($key)
    {
        $this->store->increment($key);
    }

    /**
     * @dataProvider validKeys
     */
    public function testDecrement($key)
    {
        $this->store->set($key, 5);

        $result = $this->store->decrement($key);
        $this->assertEquals(4, $result);
        $this->assertEquals(4, $this->store->get($key));

        $result = $this->store->decrement($key, 3);
        $this->assertEquals(1, $result);
        $this->assertEquals(1, $this->store->get($key));

        $result = $this->store->decrement($key, 0);
        $this->assertEquals(1, $result);
        $this->assertEquals(1, $this->store->get($key));
    }

    public function testDecrementFail()
    {
        $this->assertFalse($this->store->decrement('key'));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testDecrementInvalidKeys($key)
    {
        $this->store->decrement($key);
    }

    public function testAdd()
    {
        $this->assertTrue($this->store->add('key0', 'value0'));
        $this->assertEquals('value0', $this->store->get('key0'));
    }

    /**
     * @dataProvider validData
     */
    public function testAddValidData($data)
    {
        $this->store->add('key', $data);
        $this->assertEquals($data, $this->store->get('key'));
    }

    public function testAddFail()
    {
        $this->store->set('key0', 'value0');
        $this->assertFalse($this->store->add('key0', 'value2'));
        $this->assertEquals('value0', $this->store->get('key0'));
    }

    public function testAddTtl()
    {
        $this->assertTrue($this->store->add('key1', 'value', 1));
        $this->assertEquals('value', $this->store->get('key1'));
        sleep(2);
        $this->assertNull($this->store->get('key1'));

        $this->assertTrue($this->store->add('key2', 'value', new \DateInterval('PT1S')));
        $this->assertEquals('value', $this->store->get('key2'));
        sleep(2);
        $this->assertNull($this->store->get('key2'));
    }

    public function testAddExpiredTtl()
    {
        $this->store->add('key0', 'value', 0);
        $this->assertNull($this->store->get('key0'));
        $this->assertFalse($this->store->has('key0'));

        $this->store->add('key1', 'value', -1);
        $this->assertNull($this->store->get('key1'));
        $this->assertFalse($this->store->has('key1'));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidTtl
     */
    public function testAddInvalidTtl($ttl)
    {
        $this->store->add('key', 'value', $ttl);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testAddInvalidKeys($key)
    {
        $this->store->add($key, 'value0');
    }

    /**
     * @dataProvider validKeys
     */
    public function testAddValidKeys($key)
    {
        $this->store->add($key, 'value0');
        $this->assertEquals('value0', $this->store->get($key));
    }

    public function testReplace()
    {
        $this->store->set('key0', 'value0');
        $this->assertTrue($this->store->replace('key0', 'value3'));
        $this->assertEquals('value3', $this->store->get('key0'));
    }

    /**
     * @dataProvider validData
     */
    public function testReplaceValidData($data)
    {
        $this->store->set('key', 'value1');
        $this->store->replace('key', $data);
        $this->assertEquals($data, $this->store->get('key'));
    }

    public function testReplaceFail()
    {
        $this->assertFalse($this->store->replace('key0', 'value2'));
        $this->assertNull($this->store->get('key0'));
    }

    public function testReplaceTtl()
    {
        $this->store->set('key1', 'value1');
        $this->assertTrue($this->store->replace('key1', 'value', 1));
        $this->assertEquals('value', $this->store->get('key1'));
        sleep(2);
        $this->assertNull($this->store->get('key1'));

        $this->store->set('key2', 'value2');
        $this->assertTrue($this->store->replace('key2', 'value', new \DateInterval('PT1S')));
        $this->assertEquals('value', $this->store->get('key2'));
        sleep(2);
        $this->assertNull($this->store->get('key2'));
    }

    public function testReplaceExpiredTtl()
    {
        $this->store->set('key0', 'value0');
        $this->store->replace('key0', 'value', 0);
        $this->assertNull($this->store->get('key0'));
        $this->assertFalse($this->store->has('key0'));

        $this->store->set('key1', 'value1');
        $this->store->replace('key1', 'value', -1);
        $this->assertNull($this->store->get('key1'));
        $this->assertFalse($this->store->has('key1'));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidTtl
     */
    public function testReplaceInvalidTtl($ttl)
    {
        $this->store->set('key', 'value0');
        $this->store->replace('key', 'value1', $ttl);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testReplaceInvalidKeys($key)
    {
        $this->store->replace($key, 'value0');
    }

    /**
     * @dataProvider validKeys
     */
    public function testReplaceValidKeys($key)
    {
        $this->store->set($key, 'value1');
        $this->store->replace($key, 'value0');
        $this->assertEquals('value0', $this->store->get($key));
    }

    public function testTouch()
    {
        $this->store->set('key3', 'value3');
        $this->assertTrue($this->store->touch('key3', 1));
    }

    public function testTouchFail()
    {
        $this->assertFalse($this->store->touch('key0', 1));
    }

    public function testTouchTtl()
    {
        $this->store->set('key1', 'value1', 60);
        $this->assertTrue($this->store->touch('key1', 1));
        $this->assertEquals('value1', $this->store->get('key1'));
        sleep(2);
        $this->assertNull($this->store->get('key1'));

        $this->store->set('key2', 'value2', 60);
        $this->assertTrue($this->store->touch('key2', new \DateInterval('PT1S')));
        $this->assertEquals('value2', $this->store->get('key2'));
        sleep(2);
        $this->assertNull($this->store->get('key2'));
    }

    public function testTouchExpiredTtl()
    {
        $this->store->set('key0', 'value', 60);
        $this->store->touch('key0', 0);
        $this->assertNull($this->store->get('key0'));
        $this->assertFalse($this->store->has('key0'));

        $this->store->set('key1', 'value', 60);
        $this->store->touch('key1', -1);
        $this->assertNull($this->store->get('key1'));
        $this->assertFalse($this->store->has('key1'));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidTtl
     */
    public function testTouchInvalidTtl($ttl)
    {
        $this->store->touch('key', $ttl);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testTouchInvalidKeys($key)
    {
        $this->store->touch($key, 2);
    }

    /**
     * @dataProvider validKeys
     */
    public function testTouchValidKeys($key)
    {
        $this->store->set($key, 'value', 60);
        $this->assertTrue($this->store->touch($key, 2));
    }

    public function testPull()
    {
        $this->assertNull($this->store->pull('key'));
        $this->assertEquals('foo', $this->store->pull('key', 'foo'));

        $this->store->set('key', 'value');
        $this->assertEquals('value', $this->store->pull('key', 'foo'));
        // The pull will be remove item from cache.
        $this->assertNull($this->store->get('key'));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testPullInvalidKeys($key)
    {
        $this->store->pull($key);
    }

    /**
     * @dataProvider validKeys
     */
    public function testPullValidKeys($key)
    {
        $result = $this->store->pull($key, 'bar');
        $this->assertEquals('bar', $result);
    }

    public function testForever()
    {
        $this->store->set('key1', 'value1', 1);
        $this->assertTrue($this->store->forever('key1', 'value2'));

        sleep(2);
        $this->assertEquals('value2', $this->store->get('key1'));
    }

    /**
     * @dataProvider validData
     */
    public function testForeverValidData($data)
    {
        $this->store->forever('key', $data);
        $this->assertEquals($data, $this->store->get('key'));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testForeverInvalidKeys($key)
    {
        $this->store->forever($key, 'value0');
    }

    /**
     * @dataProvider validKeys
     */
    public function testForeverValidKeys($key)
    {
        $this->store->forever($key, 'value0');
        $this->assertEquals('value0', $this->store->get($key));
    }

    public function testRemember()
    {
        // 1
        $this->assertFalse($this->store->has('key1'));
        $result = $this->store->remember('key1', function () {
        });
        $this->assertNull($result);
        $this->assertTrue($this->store->has('key1'));

        // 2
        $this->assertFalse($this->store->has('key2'));
        $result = $this->store->remember('key2', function () {
            return 'value2';
        });
        $this->assertEquals('value2', $result);
        $this->assertEquals('value2', $this->store->get('key2'));

        // 3
        $this->store->set('key3', 'value3');
        $result = $this->store->remember('key3', function () {
            return 'value0';
        });
        $this->assertEquals('value3', $result);
        $this->assertEquals('value3', $this->store->get('key3'));
    }

    public function testRememberTtl()
    {
        $this->store->remember('key1', function () {
            return 'value';
        }, 1);
        $this->assertEquals('value', $this->store->get('key1'));
        sleep(2);
        $this->assertNull($this->store->get('key1'));

        $this->store->remember('key2', function () {
            return 'value';
        }, new \DateInterval('PT1S'));
        $this->assertEquals('value', $this->store->get('key2'));
        sleep(2);
        $this->assertNull($this->store->get('key2'));
    }

    public function testRememberExpiredTtl()
    {
        $this->store->remember('key0', function () {
            return 'value0';
        }, 0);
        $this->assertNull($this->store->get('key0'));
        $this->assertFalse($this->store->has('key0'));

        $this->store->remember('key1', function () {
            return 'value1';
        }, -1);
        $this->assertNull($this->store->get('key1'));
        $this->assertFalse($this->store->has('key1'));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidTtl
     */
    public function testRememberInvalidTtl($ttl)
    {
        $this->store->remember('key', function () {
            return 'value';
        }, $ttl);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testRememberInvalidKeys($key)
    {
        $this->store->remember($key, function () {
        });
    }

    /**
     * @dataProvider validKeys
     */
    public function testRememberValidKeys($key)
    {
        $this->store->remember($key, function () {
            return 'value0';
        });
        $this->assertEquals('value0', $this->store->get($key));
    }

    public function testEventCacheMissed()
    {
        $key = 'key0';
        $success = 0;

        $listener = function ($event) use ($key, &$success) {
            $this->assertEquals($key, $event->key);
            ++$success;
        };

        $emitter = new Emitter();
        $emitter->listen(['simple-cache.key.miss', 'simple-cache.key.key0.miss'], $listener);
        // non exists key
        $emitter->on('simple-cache.key.key1.miss', function () {
            throw new \LogicException();
        });

        $this->store->setEventEmitter($emitter);

        $this->store->has($key);
        $this->assertEquals(2, $success);

        $this->store->get($key);
        $this->assertEquals(4, $success);

        $this->store->getMultiple([$key]);
        $this->assertEquals(6, $success);
    }

    public function testEventCacheHit()
    {
        $key = 'key0';
        $value = 'value0';
        $success = 0;

        $listener = function ($event) use ($key, $value, &$success) {
            $this->assertEquals($key, $event->key);
            $this->assertEquals($value, $event->value);
            ++$success;
        };

        $emitter = new Emitter();
        $emitter->listen(['simple-cache.key.hit', 'simple-cache.key.key0.hit'], $listener);
        // non exists key
        $emitter->on('simple-cache.key.key1.hit', function () {
            throw new \LogicException();
        });

        $this->store->setEventEmitter($emitter);

        $this->store->set($key, $value);

        $this->store->has($key);
        $this->assertEquals(2, $success);

        $this->store->get($key);
        $this->assertEquals(4, $success);

        $this->store->getMultiple([$key]);
        $this->assertEquals(6, $success);
    }

    public function testEventCacheWritten()
    {
        $key = 'key0';
        $value = 'value0';
        $seconds = 60 * $this->store->getDefaultCacheTime();
        $success = 0;

        $listener = function ($event) use (&$key, &$value, &$seconds, &$success) {
            $this->assertEquals($key, $event->key);
            $this->assertEquals($value, $event->value);
            $this->assertEquals($seconds, $event->seconds);
            ++$success;
        };

        $emitter = new Emitter();
        $emitter->listen(['simple-cache.key.written', 'simple-cache.key.key0.written'], $listener);
        // non exists key
        $emitter->on('simple-cache.key.key1.written', function () {
            throw new \LogicException();
        });

        $this->store->setEventEmitter($emitter);

        $this->store->set($key, $value);
        $this->assertEquals(2, $success);

        $seconds = 10;
        $this->store->set($key, $value, $seconds);
        $this->assertEquals(4, $success);

        $seconds = 20;
        $this->store->setMultiple([$key => $value], $seconds);
        $this->assertEquals(6, $success);

        $seconds = 30;
        $this->store->replace($key, $value, $seconds);
        $this->assertEquals(8, $success);

        $seconds = 0;
        $this->store->forever($key, $value);
        $this->assertEquals(10, $success);

        $this->store->add($key, $value, $seconds);
        $this->assertEquals(10, $success); // noop

        $key = 'key2';
        $seconds = 60;
        $this->store->add('key2', $value, $seconds);
        $this->assertEquals(11, $success); // once
    }

    public function testEventCacheDeleted()
    {
        $key = 'key0';
        $success = 0;

        $listener = function ($event) use ($key, &$success) {
            $this->assertEquals($key, $event->key);
            ++$success;
        };

        $emitter = new Emitter();
        $emitter->listen(['simple-cache.key.deleted', 'simple-cache.key.key0.deleted'], $listener);
        // non exists key
        $emitter->on('simple-cache.key.key1.deleted', function () {
            throw new \LogicException();
        });

        $this->store->setEventEmitter($emitter);

        $this->store->set($key, 'value0');

        $this->store->delete($key);
        $this->assertEquals(2, $success);

        $this->store->delete('key1');
        $this->assertEquals(2, $success);

        $this->store->set($key, 'value0');
        $this->store->deleteMultiple([$key, 'key2']);
        $this->assertEquals(4, $success);

        $this->store->set($key, 'value0');
        $this->store->pull($key);
        $this->assertEquals(6, $success);
    }
}
