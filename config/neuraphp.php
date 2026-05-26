<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default embedding model to use. This should match one of the
    | Model enum values. If not set, AllMiniLML6V2 is used.
    |
    */
    'model' => env('NEURAPHP_MODEL', 'all-MiniLM-L6-v2'),

    /*
    |--------------------------------------------------------------------------
    | Default Quantization
    |--------------------------------------------------------------------------
    |
    | The default quantization level for model files.
    | Options: f32, f16, q4_0, q4_1
    |
    */
    'quantization' => env('NEURAPHP_QUANTIZATION', 'q4_0'),

    /*
    |--------------------------------------------------------------------------
    | Thread Count
    |--------------------------------------------------------------------------
    |
    | Number of threads to use for encoding. Defaults to 4.
    |
    */
    'threads' => env('NEURAPHP_THREADS', 4),

    /*
    |--------------------------------------------------------------------------
    | Model Path (optional)
    |--------------------------------------------------------------------------
    |
    | Override the default model file path. If not set, the package will
    | look for models in the models/ directory relative to the package root.
    |
    */
    'model_path' => env('NEURAPHP_MODEL_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Library Path (optional)
    |--------------------------------------------------------------------------
    |
    | Override the default library path for libbert_shared.so.
    | If not set, the package will search common locations.
    |
    */
    'library_path' => env('NEURAPHP_LIBRARY_PATH'),
];
