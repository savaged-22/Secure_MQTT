FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip sqlite3 libsqlite3-dev libzip-dev \
    && docker-php-ext-install pdo_sqlite pcntl bcmath sockets zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Usuario con el mismo UID/GID del host para evitar archivos propiedad de root
ARG UID=1000
ARG GID=1000
RUN groupadd -o -g ${GID} app && useradd -o -u ${UID} -g ${GID} -m app

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
USER app

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
