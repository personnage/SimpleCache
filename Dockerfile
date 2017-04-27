FROM php:7.1-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        telnet \
        zlib1g-dev \
        libmemcached-dev \
    && rm -r /var/lib/apt/lists/*

RUN pecl install memcached \
    pecl install redis \
    pecl install xdebug-2.5.0 \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-enable memcached redis xdebug

ENV MEMCACHED_HOST=memcached \
    REDIS_HOST=redis

ENV COMPOSER_HASH 669656bab3166a7aff8a7506b8cb2d1c292f042046c5a994c43155c0be6190fa0355160742ab2e1c88d40d5be660b410

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('SHA384', 'composer-setup.php') !== getenv('COMPOSER_HASH')) { echo 'Installer corrupt'; unlink('composer-setup.php'); exit(1); } echo PHP_EOL;" \
    && php composer-setup.php \
        --filename=composer \
        --install-dir=/usr/local/bin \
        --no-ansi \
        --snapshot \
    && php -r "unlink('composer-setup.php');"

# https://getcomposer.org/doc/03-cli.md#environment-variables
ENV COMPOSER_HOME=/composer \
    COMPOSER_PROCESS_TIMEOUT=60 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_DISABLE_XDEBUG_WARN=1 \
    COMPOSER_ALLOW_SUPERUSER=1

RUN composer global require "friendsofphp/php-cs-fixer" --prefer-source
RUN composer global require "pdepend/pdepend" --prefer-source
RUN composer global require "phpunit/phpunit:^5.0" --prefer-source
RUN composer global require "phpmd/phpmd" --prefer-source

ENV PATH "/composer/vendor/bin:$PATH"

ADD ./ ./

WORKDIR /var/www/html
