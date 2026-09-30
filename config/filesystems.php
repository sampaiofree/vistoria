<?php

$userImagesStorage = env('USER_IMAGES_STORAGE', 'local');
if (! in_array($userImagesStorage, ['local', 'r2'], true)) {
    throw new InvalidArgumentException('USER_IMAGES_STORAGE deve ser local ou r2.');
}
if ($userImagesStorage === 'r2'
    && (! env('R2_ASSETS_ACCESS_KEY_ID') || ! env('R2_ASSETS_SECRET_ACCESS_KEY')
        || ! env('R2_ASSETS_BUCKET') || ! env('R2_ENDPOINT'))) {
    throw new InvalidArgumentException('As credenciais e o endpoint do R2 devem estar configurados.');
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'inspection_photos' => $userImagesStorage === 'r2' ? [
            'driver' => 'scoped',
            'disk' => 'r2_assets',
            'prefix' => 'inspection-photos',
            'throw' => true,
        ] : [
            'driver' => 'local',
            'root' => env('INSPECTION_PHOTOS_ROOT', storage_path('app/private/inspection-photos')),
            'throw' => true,
        ],

        'inspection_maps' => $userImagesStorage === 'r2' ? [
            'driver' => 'scoped',
            'disk' => 'r2_assets',
            'prefix' => 'inspection-maps',
            'throw' => true,
        ] : [
            'driver' => 'local',
            'root' => env('INSPECTION_MAPS_ROOT', storage_path('app/private/inspection-maps')),
            'throw' => true,
        ],

        'branding_images' => $userImagesStorage === 'r2' ? [
            'driver' => 'scoped',
            'disk' => 'r2_assets',
            'prefix' => 'branding',
            'throw' => true,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/private/branding'),
            'throw' => true,
        ],

        'r2_assets' => [
            'driver' => 's3',
            'key' => env('R2_ASSETS_ACCESS_KEY_ID'),
            'secret' => env('R2_ASSETS_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_ASSETS_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            'visibility' => 'private',
            'throw' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
