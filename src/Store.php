<?php

namespace Personnage\SimpleCache;

use Closure;
use Psr\SimpleCache\CacheInterface;
use Personnage\SimpleCache\Event\CacheHit;
use Personnage\SimpleCache\Event\CacheMissed;
use Personnage\SimpleCache\Event\Emitter;
use Personnage\SimpleCache\Event\KeyDeleted;
use Personnage\SimpleCache\Event\KeyWritten;
use Personnage\SimpleCache\Exception\InvalidArgumentException;

abstract class Store implements CacheInterface
{
    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    private $prefix;

    /**
     * The event emitter implementation.
     *
     * @var Event\Emitter
     */
    protected $emitter;

    /**
     * The default number of minutes to store items.
     *
     * @var float|int
     */
    protected $default = 60;

    /**
     * Retrieve multiple items from the cache by key.
     *
     * @param array $keys
     *
     * @return array
     */
    abstract protected function many(array $keys);

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * If a negative or zero TTL is provided, the item MUST be deleted
     * from the cache if it exists, as it is expired already.
     *
     * @param array     $values
     * @param float|int $seconds
     *
     * @return array
     */
    abstract protected function save(array $values, $seconds = 0);

    /**
     * Removes multiple items from the cache.
     *
     * @param array $keys
     *
     * @return array
     */
    abstract protected function purge(array $keys);

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    abstract protected function flush();

    /**
     * Set the event emitter instance.
     *
     * @param Emitter $emitter
     */
    public function setEventEmitter(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        $itemKey = $this->makeKey($key);

        if (array_key_exists($itemKey, $values = $this->many([$itemKey]))) {
            $this->fireEvent('hit', [$key, $values[$itemKey]]);

            return true;
        }

        $this->fireEvent('missed', [$key]);

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $itemKey = $this->makeKey($key);

        if (array_key_exists($itemKey, $values = $this->many([$itemKey]))) {
            $this->fireEvent('hit', [$key, $values[$itemKey]]);

            return $values[$itemKey];
        }

        $this->fireEvent('missed', [$key]);

        if ($default instanceof Closure) {
            return $default($key, $this);
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $itemKey = $this->makeKey($key);

        if ($result = ($seconds = $this->toSeconds($ttl)) < 0) {
            $this->purge([$itemKey]);
        } else {
            $result = $this->save([$itemKey => $value], $seconds)[$itemKey];
        }

        $result && $this->fireEvent('write', [$key, $value, $seconds]);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $itemKey = $this->makeKey($key);

        if ($this->purge([$itemKey])[$itemKey]) {
            $this->fireEvent('delete', [$key]);
        }

        // PSR16: Deleting a value that does not exist should return true.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        $keys = $this->iteratorToArray($keys);
        $prefixedKeys = array_map([$this, 'makeKey'], $keys);
        $values = $this->many($prefixedKeys);

        // $keys         : [key0, key1]
        // $prefixedKeys : [prefix.key0, prefix.key1]
        // $values       : [prefix.key0 => value0, ...]

        $return = [];
        foreach ($prefixedKeys as $index => $itemKey) {
            if (array_key_exists($itemKey, $values)) {
                $this->fireEvent('hit', [$keys[$index], $values[$itemKey]]);
                $return[$keys[$index]] = $values[$itemKey];
                continue;
            }

            $this->fireEvent('missed', [$keys[$index]]);
            $return[$keys[$index]] = $default instanceof Closure ? $default($keys[$index], $this) : $default;
        }

        return $return;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param array $keys A list of keys that can obtained in a single operation
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if $keys is neither an array nor a Traversable,
     *                                                   or if any of the $keys are not a legal value
     *
     * @return array A list of key => value pairs
     *
     * @deprecated
     * @codeCoverageIgnore
     */
    public function getMulti(array $keys)
    {
        return $this->getMultiple($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $values = $this->iteratorToArray($values, false);
        $prefixedKeys = array_map(function ($key) {
            // $key is also allowed to be an integer
            return $this->makeKey(is_int($key) ? (string) $key : $key);
        }, $keys = array_keys($values));

        // $values       : [key0 => value0, ...]
        // $prefixedKeys : [prefix.key0 => value0, ...]

        if (($seconds = $this->toSeconds($ttl)) < 0) {
            $this->purge($prefixedKeys);
            $result = array_fill_keys($prefixedKeys, true);
        } else {
            $result = $this->save(array_combine($prefixedKeys, $values), $seconds);
        }

        $ok = 1;
        $original = array_combine($prefixedKeys, $keys);
        foreach ($result as $itemKey => $value) {
            $value && $this->fireEvent('write', [$original[$itemKey], $values[$original[$itemKey]], $seconds]);
            $ok &= $value;
        }

        return 1 === $ok;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param array                  $values A list of key => value pairs for a multiple-set operation
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if $values is neither an array nor a Traversable
     *                                                   or if any of the $values are not a legal value
     *
     * @return bool True on success and false on failure
     *
     * @deprecated
     * @codeCoverageIgnore
     */
    public function setMulti(array $values, $ttl = null)
    {
        return $this->setMultiple($values, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        $keys = $this->iteratorToArray($keys);
        $prefixedKeys = array_map([$this, 'makeKey'], $keys);

        $original = array_combine($prefixedKeys, $keys);
        foreach ($this->purge($prefixedKeys) as $itemKey => $value) {
            $value && $this->fireEvent('delete', [$original[$itemKey]]);
        }

        // PSR16: Deleting a value that does not exist should return true.
        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param array $keys A list of string-based keys to be deleted
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if $keys is neither an array nor a Traversable,
     *                                                   or if any of the $keys are not a legal value
     *
     * @return bool True if the items were successfully removed. False if there was an error
     *
     * @deprecated
     * @codeCoverageIgnore
     */
    public function deleteMulti(array $keys)
    {
        return $this->deleteMultiple($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key    The key of the item to store
     * @param int    $offset The amount by which to increment the item's value
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value
     *
     * @return int|bool
     */
    public function increment($key, $offset = 1)
    {
        return $this->incrementOrDecrement($key, function ($current) use ($offset) {
            return $current + (int) $offset;
        });
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key    The key of the item to store
     * @param mixed  $offset The amount by which to decrement the item's value
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value
     *
     * @return int|bool
     */
    public function decrement($key, $offset = 1)
    {
        return $this->incrementOrDecrement($key, function ($current) use ($offset) {
            return $current - (int) $offset;
        });
    }

    /**
     * Increment or decrement an item in the cache.
     *
     * @param string  $key      The key of the item to store
     * @param Closure $callback
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value
     *
     * @return int|bool
     */
    private function incrementOrDecrement($key, Closure $callback)
    {
        $itemKey = $this->makeKey($key);
        $values = $this->many([$itemKey]);

        if (!isset($values[$itemKey]) || !is_numeric($values[$itemKey]) || $values[$itemKey] < 0) {
            return false;
        }

        $value = max(0, $callback($values[$itemKey]));

        return $this->replace($key, $value) ? $value : false;
    }

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param string                 $key   The key of the item to store
     * @param mixed                  $value The value of the item to store, must be serializable
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value
     *
     * @return bool
     */
    public function add($key, $value, $ttl = null)
    {
        return !$this->has($key) && $this->set($key, $value, $ttl);
    }

    /**
     * Store an item in the cache if the key does exist.
     *
     * @param string                 $key   The key of the item to store
     * @param mixed                  $value The value of the item to store, must be serializable
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value
     *
     * @return bool
     */
    public function replace($key, $value, $ttl = null)
    {
        return $this->has($key) && $this->set($key, $value, $ttl);
    }

    /**
     * Set a new expiration on an item.
     *
     * @param string                 $key The key of the item to store
     * @param null|int|\DateInterval $ttl The TTL value of this item. If no value is sent and
     *                                    the driver supports TTL then the library may set a default value
     *                                    for it or let the driver take care of that
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if the $key string is not a legal value
     *
     * @return bool
     */
    public function touch($key, $ttl)
    {
        $key = $this->makeKey($key);

        if (($seconds = $this->toSeconds($ttl)) < 0) {
            $this->purge([$key]);

            return true;
        }

        if (array_key_exists($key, $values = $this->many([$key]))) {
            return $this->save([$key => reset($values)], $seconds)[$key];
        }

        return false;
    }

    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param string $key     The unique key of this item in the cache
     * @param mixed  $default Default value to return if the key does not exist
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if the $key string is not a legal value
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss
     */
    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);

        $this->delete($key);

        return $value;
    }

    /**
     * Get an item from the cache, or store the default value.
     *
     * @param string                 $key      The unique key of this item in the cache
     * @param Closure                $callback
     * @param null|int|\DateInterval $ttl      Optional. The TTL value of this item. If no value is sent and
     *                                         the driver supports TTL then the library may set a default values
     *                                         for it or let the driver take care of that
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if the $key string is not a legal value
     *
     * @return mixed
     */
    public function remember($key, Closure $callback, $ttl = null)
    {
        return $this->get($key, function ($key) use ($callback, $ttl) {
            $this->set($key, $value = $callback(), $ttl);

            return $value;
        });
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key   The key of the item to store
     * @param mixed  $value The value of the item to store, must be serializable
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value
     *
     * @return bool
     */
    public function forever($key, $value)
    {
        return $this->set($key, $value, new \DateInterval('PT0S'));
    }

    /**
     * Get the default cache time.
     *
     * @return float|int
     */
    public function getDefaultCacheTime()
    {
        return $this->default;
    }

    /**
     * Set the default cache time in minutes.
     *
     * @param float|int $minutes
     *
     * @return $this
     */
    public function setDefaultCacheTime($minutes)
    {
        $this->default = $minutes;

        return $this;
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set the cache key prefix.
     *
     * @param string $prefix The start prefix string
     * @param string $glue   Optional. Specifies a string to separate each key
     *
     * @return $this
     */
    public function setPrefix($prefix, $glue = '.')
    {
        $this->prefix = empty($prefix) ? '' : $prefix.$glue;

        return $this;
    }

    /**
     * Fire an event for this cache instance.
     *
     * @param string $eventName
     * @param array  $payload
     */
    protected function fireEvent($eventName, array $payload = [])
    {
        if ($this->emitter === null) {
            return;
        }

        switch ($eventName) {
            case 'hit':
                list($key, $value) = $payload;
                $event = new CacheHit($key, $value);
                $this->emitter->emit('simple-cache.key.hit', $event);
                $this->emitter->emit(sprintf('simple-cache.key.%s.hit', $key), $event);
                break;

            case 'missed':
                $event = new CacheMissed($payload[0]);
                $this->emitter->emit('simple-cache.key.miss', $event);
                $this->emitter->emit(sprintf('simple-cache.key.%s.miss', $payload[0]), $event);
                break;

            case 'delete':
                $event = new KeyDeleted($payload[0]);
                $this->emitter->emit('simple-cache.key.deleted', $event);
                $this->emitter->emit(sprintf('simple-cache.key.%s.deleted', $payload[0]), $event);
                break;

            case 'write':
                list($key, $value, $seconds) = $payload;
                $event = new KeyWritten($key, $value, $seconds);
                $this->emitter->emit('simple-cache.key.written', $event);
                $this->emitter->emit(sprintf('simple-cache.key.%s.written', $payload[0]), $event);
                break;
        }
    }

    /**
     * Copy the elements of an iterator into an array.
     *
     * @param \Traversable|array $iterator The iterator being copied
     * @param bool               $reset
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   if $iterator is neither an array nor a Traversable
     *
     * @return array An array containing the elements of the iterator
     */
    protected function iteratorToArray($iterator, $reset = true)
    {
        if ($iterator instanceof \Traversable) {
            if ($reset) {
                return iterator_to_array($iterator, false);
            }

            $values = [];
            foreach ($iterator as $key => $value) {
                if (!is_string($key) && !is_int($key)) {
                    // @see Cache\IntegrationTests\SimpleCacheTest::testSetMultipleInvalidKeys
                    $type = is_object($key) ? get_class($key) : gettype($key);
                    throw new InvalidArgumentException("Cache key must be string, $type given");
                }
                $values[$key] = $value;
            }

            return $values;
        }

        if (!is_array($iterator)) {
            $type = is_object($iterator) ? get_class($iterator) : gettype($iterator);
            throw new InvalidArgumentException("Iterator must be an array or a Traversable, $type given");
        }

        return $iterator;
    }

    /**
     * Format the key for a cache item.
     *
     * @param mixed $key The key to validate
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value
     *
     * @return string
     */
    protected function makeKey($key)
    {
        static $prefix = null;

        $this->validate($key);

        if ($prefix === null || $prefix !== $this->prefix) {
            $this->validate($this->prefix.$key);
            $prefix = $this->prefix;
        }

        return $prefix.$key;
    }

    /**
     * Validates a cache key according to PSR-16.
     *
     * @param mixed $key The key to validate
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value
     */
    protected function validate($key)
    {
        if (!is_string($key)) {
            $type = is_object($key) ? get_class($key) : gettype($key);
            throw new InvalidArgumentException("Cache key must be string, $type given");
        }

        if ('' === $key) {
            throw new InvalidArgumentException('Cache key length must be greater than zero');
        }

        if (false !== strpbrk($key, '{}()/\@:')) {
            throw new InvalidArgumentException("Cache key $key contains reserved characters {}()/\@:");
        }
    }

    /**
     * The Time To Live (TTL) of an item is the amount of time between
     * when that item is stored and it is considered stale.
     *
     * @param null|int|\DateInterval $ttl The TTL is normally defined by an integer
     *                                    representing time in seconds, or a DateInterval object
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return int
     */
    protected function toSeconds($ttl)
    {
        if ($ttl === null) {
            return (int) $this->default * 60;
        }

        if (is_int($ttl)) {
            // PSR16: Value must expire if $ttl `0`.
            return $ttl === 0 ? -1 : $ttl;
        }

        if ($ttl instanceof \DateInterval) {
            $time = time();

            return (new \DateTime("@$time"))->add($ttl)->getTimestamp() - $time;
        }

        $type = is_object($ttl) ? get_class($ttl) : gettype($ttl);
        throw new InvalidArgumentException("Expiration date must be an integer, a DateInterval or null, $type given");
    }
}
