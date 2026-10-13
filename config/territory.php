<?php

// Nilai default. `php artisan territory:install` menulis config/territory.php di aplikasi sesuai pilihan.
return [
    'mode' => 'dependent',
    'prefix' => 'territory_',
    'level' => 'village',
    'extras' => [],
    'boundaries' => [
        'levels' => [],
        'provinces' => 'all',
    ],
    'dataset_version' => null,
    'data_path' => null,
    'boundary_path' => storage_path('app/territory/boundaries'),
    'source' => [
        'urls' => [
            'https://indonesia-region.caturseptian.site',
            'https://indonesia-region-api.caturseptian-dev.workers.dev',
        ],
    ],
    'api' => [
        'enabled' => false,
        'prefix' => 'api/territory',
        'middleware' => ['api', 'throttle:60,1'],
    ],
];
