<?php

return [
    'limits' => [
        'max_dimension' => 16_384,
        'max_pixels' => 60_000_000,
    ],

    'processing' => [
        'memory_megabytes' => 256,
        'map_megabytes' => 512,
        'disk_megabytes' => 1024,
        'time_seconds' => 120,
        'threads' => 1,
        'unsafe_image_message' => 'A fotografia excede os limites seguros de processamento.',
    ],
];
