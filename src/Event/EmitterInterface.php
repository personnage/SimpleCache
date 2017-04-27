<?php

namespace Personnage\SimpleCache\Event;

interface EmitterInterface
{
    /**
     * Adds the listener function to the end of the listeners array
     * for the event named eventName.
     *
     * @param string   $eventName The name of the event
     * @param \Closure $listener  The callback function
     *
     * @return $this
     */
    public function on($eventName, \Closure $listener);

    /**
     * Adds a one time listener function for the event named eventName.
     *
     * The next time eventName is triggered, this listener is removed and then invoked.
     *
     * @param string   $eventName The name of the event
     * @param \Closure $listener  The callback function
     *
     * @return $this
     */
    public function once($eventName, \Closure $listener);

    /**
     * Check whether an event has listeners.
     *
     * @param string $eventName The name of the event
     *
     * @return bool
     */
    public function hasListeners($eventName);

    /**
     * Get all of the listeners for a given event name.
     *
     * @param string $eventName The name of the event
     *
     * @return array
     */
    public function getListeners($eventName);

    /**
     * Removes the specified listener from the listener array for the event
     * named eventName or removes all listeners of the specified eventName.
     *
     * @param string        $eventName The name of the event
     * @param \Closure|null $listener  The callback function
     *
     * @return $this
     */
    public function removeListener($eventName, \Closure $listener = null);

    /**
     * Returns an array listing the events for which the emitter has registered listeners.
     *
     * @return array
     */
    public function eventNames();

    /**
     * Synchronously calls each of the listeners registered for the event named eventName,
     * in the order they were registered, passing the supplied arguments to each.
     *
     * @param string $eventName
     * @param Event  $event
     */
    public function emit($eventName, Event $event);
}
