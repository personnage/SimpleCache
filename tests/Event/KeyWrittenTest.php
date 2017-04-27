<?php

namespace Personnage\SimpleCache\Tests;

use Personnage\SimpleCache\Event\KeyWritten;

class KeyWrittenTest extends \PHPUnit\Framework\TestCase
{
    protected $key = 'test key';
    protected $value = 'test value';
    protected $seconds = 10;

    protected $time;
    protected $event;

    public function setUp()
    {
        $this->event = new KeyWritten($this->key, $this->value, $this->seconds);
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

    public function testEventGetValueAttribute()
    {
        $this->assertObjectHasAttribute('value', $this->event);
        $this->assertEquals($this->value, $this->event->value);
    }

    public function testEventGetDurationAttribute()
    {
        $this->assertObjectHasAttribute('seconds', $this->event);
        $this->assertEquals($this->seconds, $this->event->seconds);
    }
}
