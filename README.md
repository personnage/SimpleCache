# SimpleCache: is a caching library for PHP

A PSR-16 cache implementation using unified API for various caching backends.

SimpleCache supports popular caching backends like Memcached and Redis out of the box.

[![PHP Version](https://img.shields.io/badge/php-5.5%2B-blue.svg?style=flat-square)](https://packagist.org/packages/personnage/simple-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://travis-ci.org/personnage/simple-cache.svg?style=flat-square&branch=master)](https://travis-ci.org/personnage/simple-cache)
[![codecov](https://codecov.io/gh/personnage/simple-cache/branch/master/graph/badge.svg?style=flat-square)](https://codecov.io/gh/personnage/simple-cache)
[![Quality Score](https://img.shields.io/scrutinizer/g/personnage/simple-cache.svg?style=flat-square)](https://scrutinizer-ci.com/g/personnage/simple-cache/?branch=master)
[![StyleCI](https://styleci.io/repos/79728858/shield?style=flat-square&branch=master)](https://styleci.io/repos/79728858)

---

## Installation

To install SimpleCache, simply:

```bash
$ composer require personnage/simple-cache
✨🍰✨
```

#### The list below shows the supported storage:

- [Redis](https://redis.io/)
- [Memcached](https://memcached.org/)

>Before using a Redis cache, you will need to either install the [Predis](https://packagist.org/packages/predis/predis) package via Composer or install the [PhpRedis](https://pecl.php.net/package/redis) PHP extension via PECL.

---

>Using the Memcached driver requires the [Memcached PECL](https://pecl.php.net/package/memcached) package to be installed.

## Cache Usage

#### Obtaining A Cache Instance

```php
<?php

$client = new Memcached;
$client->addServer('127.0.0.1', 11211);

$cache = new \Personnage\SimpleCache\Memcached($client, $prefix);
```

#### Retrieving items from the cache

The `get` method is used to retrieve items from the cache. If the item does not exist in the cache, null will be returned. If you wish, you may pass a second argument to the get method specifying the default value you wish to be returned if the item doesn't exist:

```php
$cache->get('key');

$cache->get('key', 'default');
```

You may even pass a `Closure` as the default value. The result of the Closure will be returned if the specified item does not exist in the cache. Passing a Closure allows you to defer the retrieval of default values:

```php
$cache->get('key', function ($key) {

    $filename = $key . '.cache';

    return file_get_contents($filename);
});
```

###### Checking for item existence

The `has` method may be used to determine if an item exists in the cache:

```php
if ($cache->has('key')) {
    //
}
```

###### Incrementing / Decrementing values

The `increment` and `decrement` methods may be used to adjust the value of integer items in the cache. Both of these methods accept an optional second argument indicating the amount by which to increment or decrement the item's value:

```php
$this->increment('key');
$this->increment('key', $offset);

$this->decrement('key');
$this->decrement('key', $offset);
```

###### Retrieve & Store

Sometimes you may wish to retrieve an item from the cache, but also store a default value if the requested item doesn't exist. You may do this using the `remember` method:

```php
$value = $cache->remember('key', function ($key) {

    $filename = $key . '.cache';

    return file_get_contents($filename);
});
```

If the item does not exist in the cache, the `Closure` passed to the remember method will be executed and its result will be placed in the cache.

###### Retrieve & Delete

If you need to retrieve an item from the cache and then delete the item, you may use the `pull` method:

```php
$cache->pull('key');
```


#### Storing items in the cache

You may use the `set` method to store items in the cache:

```php
$cache->set('key', 'value', $ttl);
```

###### Storing items forever

The `forever` method may be used to store an item in the cache permanently. Since these items will not expire, they must be manually removed from the cache using the `delete` method:

```php
$cache->forever('key', 'value');
```

>If you are using the Memcached backend, items that are stored "forever" may be removed when the cache reaches its size limit.

###### Store if not present

The `add` method will only add the item to the cache if it does not already exist in the cache store. The method will return `true` if the item is actually added to the cache. Otherwise, the method will return `false`:

```php
$cache->add('key', 'value', $ttl);
```

###### Replace if already exist

The `replace` method will only replace the item to the cache if it already exist in the cache store. The method will return `true` if the item is actually added to the cache. Otherwise, the method will return `false`:

```php
$cache->replace('key', 'value', $ttl);
```

###### Set a new expiration on an item.

The method will return `true` on success. Otherwise, the method will return `false`:

```php
$cache->touch('key', $ttl);
```


#### Removing items from the cache

You may remove items from the cache using the `delete` method:

```php
$cache->delete('key');
```

You may clear the entire cache using the `clear` method:

```php
$cache->clear();
```

> **Warning**: Flushing the cache does not respect the cache prefix and will remove all entries from the cache. Consider this carefully when clearing a cache which is shared by other applications.

## Cache Registry

The `Cache` class lets you configure global stores that you can then statically access from anywhere. Using the `Cache` registry, you may access various cache stores via the store method.

```php

$store = new \Personnage\SimpleCache\Redis($client);

Cache::addStore($store);

if (Cache::store('redis')->has('key')) {
    Cache::store('redis')->get('key');
} else {
    Cache::store('redis')->set('key', 'value');
}
```

#### Default store

```php

$store = new \Personnage\SimpleCache\Memcached($client);

Cache::addStore($store, 'default');

if (Cache::has('key')) {
    Cache::get('key');
} else {
    Cache::set('key', 'value');
}
```

## Using Events

To execute code on every cache operation, you may listen for the `events` fired by the cache.

The following events are dispatched:

- `simple-cache.key.hit`
- `simple-cache.key.[key name].hit`
- `simple-cache.key.miss`
- `simple-cache.key.[key name].miss`
- `simple-cache.key.written`
- `simple-cache.key.[key name].written`
- `simple-cache.key.deleted`
- `simple-cache.key.[key name].deleted`

```php
<?php

use Personnage\SimpleCache\Event\Emitter;

$emitter = new Emitter();
$emitter->on('simple-cache.key.hit', $listener);
$emitter->on('simple-cache.key.log.deleted', $listener);
$emitter->once('simple-cache.key.log.users.written', $listener);

$cache->setEventEmitter($emitter);

$cache->get('item');               // simple-cache.key.hit
$cache->delete('log');             // simple-cache.key.log.deleted
$cache->set('log.users', 'value'); // simple-cache.key.log.users.written
$cache->set('log.users', 'value'); // skip

```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

```bash
$ composer test                            # run all unit tests
$ composer testOnly TestClass              # run specific unit test class
$ composer testOnly TestClass::testMethod  # run specific unit test method
$ composer check-style                     # check code style for errors
$ composer fix-style                       # automatically fix code style errors
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Acknowledgements

SimpleCache is heavily inspired by [Illuminate Cache](https://github.com/illuminate/cache) and [Scrapbook](https://github.com/matthiasmullie/scrapbook)
