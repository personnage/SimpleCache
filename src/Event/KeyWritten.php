<?php

namespace Personnage\SimpleCache\Event;

class KeyWritten extends Event
{
    public $value;
    public $seconds;

    public function __construct($key, $value, $seconds)
    {
        parent::__construct($key);

        $this->value = $value;
        $this->seconds = $seconds;
    }
}
