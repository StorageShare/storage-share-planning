<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Compression Settings
    |--------------------------------------------------------------------------
    |
    | Deze instellingen bepalen hoe afbeeldingen worden gecomprimeerd
    | voordat ze worden opgeslagen.
    |
    */

    'compression' => [
        // Maximale bestandsgrootte in bytes (2MB standaard)
        'max_file_size' => env('IMAGE_MAX_SIZE_BYTES', 2097152), // 2MB

        // Kwaliteitsinstellingen
        'quality' => [
            'initial' => env('IMAGE_INITIAL_QUALITY', 90),
            'minimum' => env('IMAGE_MINIMUM_QUALITY', 50),
            'step' => env('IMAGE_QUALITY_STEP', 10),
        ],

        // Resize instellingen
        'resize' => [
            'minimum_ratio' => env('IMAGE_MINIMUM_RESIZE_RATIO', 0.3),
            'step' => env('IMAGE_RESIZE_STEP', 0.1),
        ],

        // Ondersteunde bestandsformaten
        'supported_formats' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp'
        ],

        // Fallback bij compressiefout
        'fallback_enabled' => env('IMAGE_FALLBACK_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configuratie voor waar en hoe afbeeldingen worden opgeslagen.
    |
    */

    'storage' => [
        // Standaard disk voor afbeeldingen
        'default_disk' => env('IMAGE_STORAGE_DISK', 'public'),

        // Directory structuur
        'directories' => [
            'task_photos' => 'task-photos',
            'planning_task_photos' => 'planning-task-photos',
            'completion_photos' => 'planning-task-completion-photos',
        ],
    ],
]; 