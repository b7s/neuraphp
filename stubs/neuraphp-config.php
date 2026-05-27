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
    'model' => 'all-MiniLM-L6-v2',

    /*
    |--------------------------------------------------------------------------
    | Default Quantization
    |--------------------------------------------------------------------------
    |
    | The default quantization level for model files.
    | Options: f32, f16, q4_0, q4_1
    | Q4_0 offers the best balance of speed and quality for most use cases.
    |
    */
    'quantization' => 'q4_0',

    /*
    |--------------------------------------------------------------------------
    | Thread Count
    |--------------------------------------------------------------------------
    |
    | Number of threads to use for encoding. Defaults to 4.
    | Set to 0 to use all available cores.
    |
    */
    'threads' => 4,

    /*
    |--------------------------------------------------------------------------
    | Pooling Mode
    |--------------------------------------------------------------------------
    |
    | How to pool token embeddings into a single vector.
    | Options: mean, cls, last
    |
    */
    'pooling_mode' => 'mean',

    /*
    |--------------------------------------------------------------------------
    | Model Path (optional)
    |--------------------------------------------------------------------------
    |
    | Override the default model file path. If not set, the package will
    | look for models in bin/neuraphp/models/ relative to the project root.
    |
    */
    'model_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Library Path (optional)
    |--------------------------------------------------------------------------
    |
    | Override the default library path for libbert_shared.so.
    | If not set, the package will search bin/neuraphp/lib/ and system paths.
    |
    */
    'library_path' => null,
];
