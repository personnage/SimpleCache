<?php

namespace Personnage\SimpleCache;

final class Memory extends Store
{
    /**
     * @var array
     */
    private $store = [];

    /**
     * @param string $prefix The start prefix string
     */
    public function __construct($prefix = '')
    {
        $this->setPrefix($prefix);
    }

    /**
     * $cache = Memory::create();
     * $cache->has('key0');.
     *
     * @param string $prefix The start prefix string
     *
     * @return Memory
     */
    public static function create($prefix = '')
    {
        return new self($prefix);
    }

    /**
     * {@inheritdoc}
     */
    protected function many(array $keys)
    {
        $return = [];
        foreach ($keys as $key) {
            if (isset($this->store[$key])) {
                list($value, $ttl) = $this->store[$key];

                if ($ttl === 0 || $ttl > time()) {
                    $return[$key] = $value;
                    continue;
                }
                unset($this->store[$key]);
            }
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    protected function save(array $values, $seconds = 0)
    {
        $ttl = $this->toTimestamp($seconds);

        foreach ($values as $key => $value) {
            // Object in cache should not have their values changed.
            // @see SimpleCacheTest::testObjectDoesNotChangeInCache
            $this->store[$key] = [is_object($value) ? clone $value : $value, $ttl];
        }

        return array_fill_keys(array_keys($values), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function purge(array $keys)
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = isset($this->store[$key]);
            unset($this->store[$key]);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function flush()
    {
        $this->store = [];

        return true;
    }

    protected function toTimestamp($seconds)
    {
        return $seconds > 0 ? time() + $seconds : 0;
    }
}
