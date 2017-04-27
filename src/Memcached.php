<?php

namespace Personnage\SimpleCache;

use Personnage\SimpleCache\Exception\InvalidKeyException;

final class Memcached extends Store
{
    /**
     * The Memcached instance.
     *
     * @var \Memcached
     */
    private $client;

    /**
     * @param \Memcached $client The Memcached instance
     * @param string     $prefix The start prefix string
     */
    public function __construct(\Memcached $client, $prefix = '')
    {
        $this->client = $client;
        $this->setPrefix($prefix);
    }

    /**
     * $cache = Memcached::create();
     * $cache->has('key0');.
     *
     * @param array  $servers Array of the servers to add to the pool
     * @param string $prefix  The start prefix string
     *
     * @return Memcached
     */
    public static function create(array $servers = [], $prefix = '')
    {
        if (empty($servers)) {
            $servers = [['127.0.0.1', 11211]];
        } elseif (!is_array(reset($servers))) {
            // Each entry in servers is supposed to be an array containing
            // hostname, port, and, optionally, weight of the server.
            $servers = [$servers];
        }

        $memcached = new \Memcached();
        $memcached->addServers($servers);

        return new self($memcached, $prefix);
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key The cache item key
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @deprecated
     *
     * @return bool
     */
    protected function exists($key)
    {
        return $this->client->get($this->makeKeyInternal($key)) !== false
            || $this->client->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function many(array $keys)
    {
        if (empty($keys)) {
            return [];
        }

        $memcachedKeys = array_map([$this, 'makeKeyInternal'], $keys);
        $values = $this->client->getMulti($memcachedKeys);

        // We expect false or empty array
        if (empty($values)) {
            return [];
        }

        $map = array_combine($memcachedKeys, $keys);
        $result = [];
        foreach ($values as $key => $value) {
            $result[$map[$key]] = $value;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function save(array $values, $seconds = 0)
    {
        $keys = array_keys($values);
        $memcachedKeys = array_map([$this, 'makeKeyInternal'], $keys);

        $ttl = $this->toTimestamp($seconds);
        $result = $this->client->setMulti(array_combine($memcachedKeys, $values), $ttl);

        return array_fill_keys($keys, $result);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function purge(array $keys)
    {
        if (empty($keys)) {
            return [];
        }

        $memcachedKeys = array_map([$this, 'makeKeyInternal'], $keys);

        $values = $this->client->deleteMulti($memcachedKeys);
        $map = array_combine($memcachedKeys, $keys);

        $result = [];

        foreach ($values as $key => $value) {
            $result[$map[$key]] = (
                true === $value || \Memcached::RES_SUCCESS === $value
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function flush()
    {
        return $this->client->flush();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function increment($key, $offset = 1)
    {
        $key = $this->makeKey($key);

        return $this->client->increment($this->makeKeyInternal($key), $offset);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function decrement($key, $offset = 1)
    {
        $key = $this->makeKey($key);

        return $this->client->decrement($this->makeKeyInternal($key), $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $ttl = null)
    {
        return $this->addOrReplace('add', $key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $ttl = null)
    {
        return $this->addOrReplace('replace', $key, $value, $ttl);
    }

    /**
     * Add or replace an item in the cache.
     *
     * @param string                 $method The method name
     * @param string                 $key    The key of the item to store
     * @param mixed                  $value  The value of the item to store, must be serializable
     * @param null|int|\DateInterval $ttl    The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return bool
     */
    private function addOrReplace($method, $key, $value, $ttl)
    {
        $itemKey = $this->makeKey($key);

        if (($seconds = $this->toSeconds($ttl)) < 0) {
            $this->purge([$this->makeKeyInternal($itemKey)]);

            return true;
        }

        if ($result = $this->client->$method($this->makeKeyInternal($itemKey), $value, $this->toTimestamp($seconds))) {
            $this->fireEvent('write', [$key, $value, $seconds]);
        }

        return $result;
    }

    protected function toTimestamp($seconds)
    {
        return $seconds > 0 ? time() + $seconds : 0;
    }

    /**
     * Construct a cache key.
     *
     * @param string $key
     *
     * @throws InvalidKeyException
     *
     * @return string
     *
     * @see https://github.com/wikimedia/mediawiki/commit/be76d869#diff-75b7c03970b5e43de95ff95f5faa6ef1R100
     * @see https://github.com/wikimedia/mediawiki/blob/master/includes/libs/objectcache/MemcachedBagOStuff.php#L116
     */
    protected function makeKeyInternal($key)
    {
        $encode = function (array $match) {
            return rawurlencode($match[0]);
        };

        // We encode spaces and line breaks to avoid protocol errors. We encode
        // the other control characters for compatibility with libmemcached
        // verify_key. We leave other punctuation alone, to maximise backwards
        // compatibility.
        $key = preg_replace_callback('/[^\x21-\x22\x24\x26-\x39\x3b-\x7e]+/', $encode, $key);

        if (strlen($key) > 255) {
            throw new InvalidKeyException('Encoded Memcached keys can not exceed 255 chars');
        }

        return $key;
    }
}
