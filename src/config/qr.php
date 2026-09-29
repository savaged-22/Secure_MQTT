<?php

return [
    // Vida del token en segundos
    'ttl' => (int) env('QR_TOKEN_TTL', 45),

    // Clave de firma HMAC. Si está vacía se deriva de APP_KEY.
    'secret' => env('QR_TOKEN_SECRET'),
];
