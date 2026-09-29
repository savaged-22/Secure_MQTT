<?php

return [
    // Zona horaria del campus: las reglas de horario se evalúan en esta zona.
    // (La base de datos y el resto de la app siguen en UTC.)
    'timezone' => env('ACCESS_TIMEZONE', 'America/Bogota'),

    // Los torniquetes de salida solo exigen un QR válido (nadie queda atrapado en un edificio).
    'free_exit' => (bool) env('ACCESS_FREE_EXIT', true),
];
