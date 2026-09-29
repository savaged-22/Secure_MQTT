<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'turnstile'),
    'password' => env('RABBITMQ_PASSWORD', 'turnstile_pass'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    // Regla de php-amqplib: read_write_timeout debe ser al menos el doble del heartbeat
    'heartbeat' => 30,
    'read_write_timeout' => 65,
    'connection_timeout' => 5,
];
