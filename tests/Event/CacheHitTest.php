<?php

namespace Personnage\SimpleCache\Tests;

use Personnage\SimpleCache\Event\CacheHit;

class CacheHitTest extends \PHPUnit\Framework\TestCase
{
    protected $key = 'test key';
    protected $value = 'test value';
    protected $event;

    public function setUp()
    {
        $this->event = new CacheHit($this->key, $this->value);
    }

    public function tearDown()
    {
        $this->event = null;
    }

    public function testEventGetKeyAttribute()
    {
        $this->assertObjectHasAttribute('key', $this->event);
        $this->assertEquals($this->key, $this->event->key);
    }

    public function testEventHasValueAttribute()
    {
        $this->assertObjectHasAttribute('value', $this->event);
        $this->assertEquals($this->value, $this->event->value);
    }
}
