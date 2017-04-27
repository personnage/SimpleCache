<?php

namespace Personnage\SimpleCache\Tests;

use Personnage\SimpleCache\Event\Emitter;
use Personnage\SimpleCache\Event\Event;

class EmitterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Personnage\SimpleCache\Event\Emitter
     */
    protected $emitter;

    public function setUp()
    {
        $this->emitter = new Emitter();
    }

    public function tearDown()
    {
        $this->emitter = null;
    }

    public function testEmptyListeners()
    {
        $this->assertEquals([], $this->emitter->eventNames());
    }

    public function testOnCallFluent()
    {
        $emitter = $this->emitter->on('event', function () {
        });

        $this->assertSame($this->emitter, $emitter);
    }

    public function testOnceCallFluent()
    {
        $emitter = $this->emitter->once('event', function () {
        });

        $this->assertSame($this->emitter, $emitter);
    }

    public function testOn()
    {
        $eventName = 'event';

        $this->assertFalse($this->emitter->hasListeners($eventName));
        $this->assertEquals([], $this->emitter->getListeners($eventName));

        $emitter = $this->emitter->on($eventName, $callback = function () {
        });

        $this->assertTrue($this->emitter->hasListeners($eventName));
        $this->assertEquals([$callback], $this->emitter->getListeners($eventName));

        $this->assertEquals([$eventName], $this->emitter->eventNames());
    }

    public function testOnce()
    {
        $eventName = 'event';

        $this->assertFalse($this->emitter->hasListeners($eventName));
        $this->assertEquals([], $this->emitter->getListeners($eventName));

        $emitter = $this->emitter->once($eventName, $callback = function () {
        });

        $this->assertTrue($this->emitter->hasListeners($eventName));
        $this->assertEquals([$callback], $this->emitter->getListeners($eventName));

        $this->assertEquals([$eventName], $this->emitter->eventNames());
    }

    public function testRemoveAllListener()
    {
        $eventName = 'event';

        $this->emitter->on($eventName, $callbackOne = function () {
        });
        $this->emitter->on($eventName, $callbackTwo = function () {
        });

        $this->assertEquals([$callbackOne, $callbackTwo], $this->emitter->getListeners($eventName));
        $this->emitter->removeListener($eventName);
        $this->assertEquals([], $this->emitter->getListeners($eventName));
    }

    public function testRemoveListener()
    {
        $eventName = 'event';

        $this->emitter->on($eventName, $callbackOne = function () {
        });
        $this->emitter->on($eventName, $callbackTwo = function () {
        });

        $this->assertEquals([$callbackOne, $callbackTwo], $this->emitter->getListeners($eventName));
        $this->emitter->removeListener($eventName, $callbackTwo);
        $this->assertEquals([$callbackOne], $this->emitter->getListeners($eventName));
        $this->emitter->removeListener($eventName, $callbackOne);
        $this->assertEquals([], $this->emitter->getListeners($eventName));
    }

    public function testEmitSameEvent()
    {
        $event = new Event('key0');
        $eventName = 'event';

        $success = false;
        $this->emitter->on($eventName, $callback = function ($e) use (&$success, $event) {
            $this->assertSame($event, $e);
            $success = true;
        });

        $this->emitter->emit($eventName, $event);
        $this->assertTrue($success);
    }

    public function testListen()
    {
        $this->emitter->listen('event.foo', $callbackOne = function () {
        });
        $this->assertEquals([$callbackOne], $this->emitter->getListeners('event.foo'));

        $this->emitter->listen(['event.foo', 'event.bar'], $callbackTwo = function () {
        });
        $this->assertEquals([$callbackTwo, $callbackOne], $this->emitter->getListeners('event.foo'));
        $this->assertEquals([$callbackTwo], $this->emitter->getListeners('event.bar'));
    }
}
