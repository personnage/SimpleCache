<?php

namespace Personnage\SimpleCache\Tests;

use Personnage\SimpleCache\Event\KeyDeleted;

class KeyDeletedTest extends \PHPUnit\Framework\TestCase
{
    protected $key = 'test key';
    protected $event;

    public function setUp()
    {
        $this->event = new KeyDeleted($this->key);
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
