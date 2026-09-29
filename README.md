# Control de acceso con torniquetes (Laravel + SQLite + AMQP)

## 1. Levantar el entorno por primera vez

```bash
# UID/GID del host (evita archivos de root en ./src)
printf "UID=$(id -u)\nGID=$(id -g)\n" > .env

# Construir la imagen
docker compose build

# Crear el proyecto Laravel dentro de ./src (debe estar vacío)
docker compose run --rm --no-deps app composer create-project laravel/laravel .

# Crear la base SQLite y migrar
docker compose run --rm --no-deps app sh -c "touch database/database.sqlite && php artisan migrate --force"

# Levantar el servidor
docker compose up -d
```

Abre http://localhost:8000

## 2. Configurar SQLite (src/.env)

```
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
```

Para varios workers escribiendo a la vez, en `src/config/database.php`,
dentro de la conexión `sqlite`, deja:

```php
'busy_timeout' => 5000,
'journal_mode' => 'wal',
'synchronous'  => 'normal',
```

## 3. Comandos útiles

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose exec app composer require php-amqplib/php-amqplib
docker compose logs -f app
docker compose down
```
