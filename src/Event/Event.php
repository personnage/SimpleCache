<?php

namespace Personnage\SimpleCache\Event;

class Event
{
    public $key;

    public function __construct($key)
    {
        $this->key = $key;
    }
}
