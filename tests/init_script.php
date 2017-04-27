<?php

`sudo apt-get install -y --no-install-recommends zlib1g-dev libmemcached-dev`;
`sudo kill -9 $(sudo lsof -t -i:6379) $(sudo lsof -t -i:11211)`;

`docker run -d -p 6379:6379 redis`;
`docker run -d -p 11211:11211 memcached`;

`pecl uninstall redis`;
`pecl uninstall memcached`;

if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
    `(printf "no --disable-memcached-sasl\n" | pecl install memcached)`;
    `pecl install redis`;
} else {
    `(printf "no --disable-memcached-sasl\n" | pecl install memcached-2.2.0)`;
    `pecl install redis-2.2.8`;
}
