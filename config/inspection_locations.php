<?php

return [
    'limits' => [
        'maps_per_inspection' => 100,
        'maps_per_category' => 25,
        'markers_per_map' => 500,
        'assessments_per_editor' => 1000,
        'photos_per_assessment' => 100,
        'source_size_kilobytes' => 50 * 1024,
        'image_dimension' => 30_000,
        'image_pixels' => 80_000_000,
        'pdf_pages' => 200,
    ],

    'processing' => [
        'max_output_dimension' => 3200,
        'thumbnail_dimension' => 640,
        'memory_megabytes' => 256,
        'map_megabytes' => 512,
        'disk_megabytes' => 1024,
        'time_seconds' => 120,
        'error_message' => 'Não foi possível processar a imagem-base. Verifique o arquivo e tente novamente.',
    ],
];
