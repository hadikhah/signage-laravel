<?php

return [
    /*
     * Expand IP lookup to all loopback variants (127.0.0.1, ::1, ::ffff:127.0.0.1).
     * Defaults to true in local/development/testing, false elsewhere.
     *
     * MUST use env('APP_ENV') here, not app()->environment() —
     * config files load before the `env` container binding exists.
     */
    'expand_loopback_variants' => (bool) env(
        'SIGNAGE_EXPAND_LOOPBACK',
        in_array(env('APP_ENV', 'production'), ['local', 'development', 'testing'], true),
    ),
];
