<?php

namespace Personnage\SimpleCache\Event;

class Emitter implements EmitterInterface
{
    /**
     * The registered listeners.
     *
     * @var array
     */
    private $listeners = [];

    /**
     * Register an event listener with the dispatcher.
     *
     * @param string|array $events
     * @param \Closure     $listener
     */
    public function listen($events, \Closure $listener)
    {
        foreach ((array) $events as $eventName) {
            $this->on($eventName, $listener);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function on($eventName, \Closure $listener)
    {
        $this->listeners[$eventName][] = $listener;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function once($eventName, \Closure $listener)
    {
        return $this->on($eventName, $this->wrap($this, $listener));
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners($eventName)
    {
        return isset($this->listeners[$eventName]) && count($this->listeners[$eventName]);
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners($eventName)
    {
        if ($this->hasListeners($eventName)) {
            return $this->listeners[$eventName];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function removeListener($eventName, \Closure $listener = null)
    {
        if (!$listener) {
            unset($this->listeners[$eventName]);
        }

        if (!isset($this->listeners[$eventName])) {
            return $this;
        }

        if (false !== ($key = array_search($listener, $this->listeners[$eventName], true))) {
            unset($this->listeners[$eventName][$key]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function eventNames()
    {
        return array_keys($this->listeners);
    }

    /**
     * {@inheritdoc}
     */
    public function emit($eventName, Event $event)
    {
        foreach ($this->getListeners($eventName) as $listener) {
            $listener($event, $eventName);
        }
    }

    protected function wrap(self $emitter, \Closure $listener)
    {
        $context = (object) [];
        $context->wrapper = function (Event $event, $eventName) use ($emitter, $listener) {
            $emitter->removeListener($eventName, $this->wrapper);

            $listener($event, $eventName);
        };

        $context->wrapper->bindTo($context);

        return $context->wrapper;
    }
}
