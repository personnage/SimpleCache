<?php

namespace Personnage\SimpleCache;

final class Cache
{
    /**
     * @var Store[]
     */
    private static $stores = [];

    /**
     * @var string
     */
    private static $default = 'default';

    /**
     * Add new store instance to the registry.
     *
     * @param Store       $store Instance of the store
     * @param string|null $name  Name of the store instance (the class name by default)
     *
     * @throws Exception\InvalidArgumentException
     */
    public static function addStore(Store $store, $name = null)
    {
        $name = $name ?: strtolower(basename(str_replace('\\', '/', get_class($store))));

        if (isset(self::$stores[$name])) {
            throw new Exception\InvalidArgumentException('Store with the given name already exists');
        }

        static::$stores[$name] = $store;
    }

    /**
     * Checks if such store exists by name or instance.
     *
     * @param Store|string $store Name or store instance
     *
     * @return bool
     */
    public static function hasStore($store)
    {
        if ($store instanceof Store) {
            return in_array($store, static::$stores, true);
        }

        return isset(self::$stores[$store]);
    }

    /**
     * Checks if default store exists.
     *
     * @return bool
     */
    public static function hasDefaultStore()
    {
        return isset(self::$stores[static::$default]);
    }

    /**
     * Removes instance from registry by name or instance.
     *
     * @param Store|string $store Name or store instance
     */
    public static function removeStore($store)
    {
        if ($store instanceof Store) {
            if (false !== ($key = array_search($store, static::$stores, true))) {
                unset(static::$stores[$key]);
            }

            return;
        }

        unset(static::$stores[$store]);
    }

    /**
     * Clears the registry.
     */
    public static function removeAllStores()
    {
        self::$stores = [];
    }

    /**
     * Get a cache store instance by name.
     *
     * @param string $name Name of the requested Store instance
     *
     * @throws Exception\InvalidArgumentException
     *
     * @return Store
     */
    public static function store($name)
    {
        if (isset(self::$stores[$name])) {
            return self::$stores[$name];
        }

        throw new Exception\InvalidArgumentException("Requested $name store instance is not in the registry");
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param mixed  $arguments
     *
     * @throws Exception\InvalidArgumentException
     *
     * @return mixed
     */
    public static function __callStatic($method, $arguments)
    {
        return call_user_func_array([self::store(self::$default), $method], $arguments);
    }
}
