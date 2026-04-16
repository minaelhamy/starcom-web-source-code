<?php

return [
    'base_url' => env('CARTONA_BASE_URL'),
    'authorization_token' => env('CARTONA_AUTHORIZATION_TOKEN'),
    'timeout' => (int) env('CARTONA_TIMEOUT', 30),
    'country' => env('CARTONA_COUNTRY', 'Egypt'),
    'default_country_code' => env('CARTONA_DEFAULT_COUNTRY_CODE', '+20'),
];
