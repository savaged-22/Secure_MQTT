#!/bin/sh
set -e

# Solo actúa si el proyecto Laravel ya fue creado
if [ -f artisan ]; then
    [ -d vendor ] || composer install --no-interaction
    mkdir -p database
    [ -f database/database.sqlite ] || touch database/database.sqlite
fi

exec "$@"
