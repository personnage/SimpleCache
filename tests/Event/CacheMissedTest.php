<?php

namespace Personnage\SimpleCache\Tests;

use Personnage\SimpleCache\Event\CacheMissed;

class CacheMissedTest extends \PHPUnit\Framework\TestCase
{
    protected $key = 'test key';
    protected $event;

    public function setUp()
    {
        $this->event = new CacheMissed($this->key);
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
}
