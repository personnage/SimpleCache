<?php

namespace Personnage\SimpleCache\Event;

class CacheHit extends Event
{
    public $value;

    public function __construct($key, $value)
    {
        parent::__construct($key);

        $this->value = $value;
    }
}
