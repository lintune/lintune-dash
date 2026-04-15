<?php

return [
    'base_url'       => env('KEYCLOAK_BASE_URL', 'https://auth.lintune.com'),
    'client_id'      => env('KEYCLOAK_CLIENT_ID', 'lintune-frontend'),
    'allowed_groups' => explode(',', env('KEYCLOAK_ALLOWED_GROUPS', 'realm-admin')),
];
