<?php

namespace Personnage\SimpleCache;

use Personnage\SimpleCache\Exception\InvalidArgumentException;

final class Redis extends Store
{
    /**
     * The Redis instance.
     *
     * @var \Predis\Client|\Redis
     */
    private $client;

    /**
     * @param \Redis|\Predis\Client $client
     * @param string                $prefix
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function __construct($client, $prefix = '')
    {
        $this->setClient($client);
        $this->setPrefix($prefix);
    }

    /**
     * $cache = Redis::create();
     * $cache->has('key0');.
     *
     * @param string|null $host
     * @param int|null    $port
     * @param string      $prefix The start prefix string
     *
     * @return Redis
     */
    public static function create($host = null, $port = null, $prefix = '')
    {
        $redis = new \Redis();
        $redis->connect(empty($host) ? '127.0.0.1' : $host, empty($port) ? 6379 : $port);

        return new self($redis, $prefix);
    }

    /**
     * @param \Redis|\Predis\Client $client
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function setClient($client)
    {
        if (!$client instanceof \Redis && !$client instanceof \Predis\Client) {
            $type = is_object($client) ? get_class($client) : gettype($client);
            throw new InvalidArgumentException(
                __METHOD__."() expects parameter 1 to be Redis or Predis\Client, $type given"
            );
        }

        $this->client = $client;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key The cache item key
     *
     * @deprecated
     *
     * @return bool
     */
    protected function exists($key)
    {
        return (bool) $this->client->exists($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function many(array $keys)
    {
        if (empty($keys)) {
            return [];
        }

        $lua = <<<'LUA'
local r={}
for _,key in pairs(KEYS) do
  r[#r+1] = redis.call('exists', key)
end
return {r,redis.call('mget',unpack(KEYS))}
LUA;

        list($exists, $values) = $this->evalScript($lua, $keys);

        $result = [];
        foreach ($values as $index => $value) {
            if ($exists[$index]) {
                $result[$keys[$index]] = self::unpickle($value);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function save(array $values, $seconds = 0)
    {
        $lua = <<<'LUA'
local r,t = {},tonumber(table.remove(ARGV, 1))
for i,k in pairs(KEYS) do
  r[#r+1] = t>0 and redis.call('setex',k,t,ARGV[i]) or redis.call('set',k,ARGV[i])
end
return r
LUA;

        $keys = array_keys($values);
        $argv = array_map([__CLASS__, 'pickle'], $values);
        array_unshift($argv, $seconds);

        $return = [];
        foreach ($this->evalScript($lua, $keys, $argv) as $index => $result) {
            $return[$keys[$index]] = is_bool($result) ? $result : 'OK' === (string) $result;
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    protected function purge(array $keys)
    {
        if (empty($keys)) {
            return [];
        }

        $values = $this->many($keys);
        $this->client->del($keys);

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = array_key_exists($key, $values);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function flush()
    {
        $this->client->flushDb();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1)
    {
        $script = <<<'LUA'
return redis.call('exists',KEYS[1])>0 and redis.call('incrBy',KEYS[1],ARGV[1])
LUA;

        return $this->incrementOrDecrement($script, $key, $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1)
    {
        $script = <<<'LUA'
return redis.call('exists',KEYS[1])>0 and redis.call('decrBy',KEYS[1],ARGV[1])
LUA;

        return $this->incrementOrDecrement($script, $key, $offset);
    }

    /**
     * Increment or decrement an item in the cache.
     *
     * @param string $script The Lua script
     * @param string $key    The key of the item to store
     * @param int    $offset The amount by which to decrement the item's value
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException MUST be thrown if the $key string is not a legal value
     *
     * @return int|bool
     */
    private function incrementOrDecrement($script, $key, $offset)
    {
        $result = $this->evalScript($script, [$this->makeKey($key)], [$offset]);

        return null === $result ? false : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $ttl = null)
    {
        $script = <<<'LUA'
return redis.call('exists',KEYS[1])<1 and redis.call('setex',KEYS[1],ARGV[2],ARGV[1])
LUA;

        return $this->addOrReplace($script, $key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $ttl = null)
    {
        $script = <<<'LUA'
return redis.call('exists',KEYS[1])>0 and redis.call('setex',KEYS[1],ARGV[2],ARGV[1])
LUA;

        return $this->addOrReplace($script, $key, $value, $ttl);
    }

    /**
     * Add or replace an item in the cache.
     *
     * @param string                 $script
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
    private function addOrReplace($script, $key, $value, $ttl)
    {
        $itemKey = $this->makeKey($key);

        if (($seconds = $this->toSeconds($ttl)) < 0) {
            $this->purge([$itemKey]);

            return true;
        }

        $result = (bool) $this->evalScript($script, [$itemKey], [self::pickle($value), (int) max(1, $seconds)]);
        $result && $this->fireEvent('write', [$key, $value, $seconds]);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $ttl)
    {
        if (($seconds = $this->toSeconds($ttl)) < 0) {
            $this->purge([$this->makeKey($key)]);

            return true;
        }

        return (bool) $this->client->expire($this->makeKey($key), $seconds);
    }

    /**
     * Used to evaluate scripts using the Lua interpreter built into Redis.
     *
     * @param string $script The Lua script
     * @param array  $keys
     * @param array  $argv
     *
     * @return array
     */
    protected function evalScript($script, array $keys = [], array $argv = [])
    {
        if ($this->client instanceof \Redis) {
            return $this->client->eval($script, array_merge($keys, $argv), count($keys));
        }

        $params = array_merge([$script, count($keys)], $keys, $argv);

        return call_user_func_array([$this->client, 'eval'], $params);
    }

    /**
     * Generates a storable representation of a value.
     *
     * @param mixed $value The value to be serialized
     *
     * @return string
     */
    private static function pickle($value)
    {
        return is_numeric($value) ? json_encode($value) : serialize($value);
    }

    /**
     * Creates a PHP value from a stored representation.
     *
     * @param string $value The serialized string
     *
     * @return mixed
     */
    private static function unpickle($value)
    {
        $result = json_decode($value);

        if (json_last_error() === \JSON_ERROR_NONE) {
            return $result;
        }

        return unserialize($value);
    }
}
