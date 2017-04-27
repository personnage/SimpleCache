.PHONY: up down build sh phpunit composer-install composer-update

build:
	docker-compose config -q && \
	docker-compose build --pull

sh:
	docker-compose config -q && \
	docker-compose run --rm simplecache bash

down:
	docker-compose config -q && \
	docker-compose down -v

phpunit:
	docker-compose config -q && \
	docker-compose run --rm simplecache phpunit --process-isolation

composer-install:
	docker-compose config -q && \
	docker-compose run --rm simplecache composer install

composer-update:
	docker-compose config -q && \
	docker-compose run --rm simplecache composer update
