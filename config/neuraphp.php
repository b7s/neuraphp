<?php

declare(strict_types=1);

return [
    /*
|--------------------------------------------------------------------------
| Default Model
|--------------------------------------------------------------------------
|
| The default embedding model to use. Can be either:
|   - A known model short name (e.g. 'all-MiniLM-L6-v2', 'bge-large-en-v1.5')
|   - A full HuggingFace ID (e.g. 'BAAI/bge-large-en-v1.5', 'intfloat/e5-base-v2')
|
| If not set, all-MiniLM-L6-v2 is used.
|
*/
    'model' => env('NEURAPHP_MODEL', 'all-MiniLM-L6-v2'),

    /*
|--------------------------------------------------------------------------
| Model Dimensions (optional, for custom models)
|--------------------------------------------------------------------------
|
| The number of embedding dimensions. Only needed for custom models
| not present in the Model enum. Known models have dimensions built-in.
|
*/
    'model_dimensions' => env('NEURAPHP_MODEL_DIMENSIONS'),

    /*
|--------------------------------------------------------------------------
| Model Max Tokens (optional, for custom models)
|--------------------------------------------------------------------------
|
| The maximum number of tokens the model can process.
| Only needed for custom models not present in the Model enum.
|
*/
    'model_max_tokens' => env('NEURAPHP_MODEL_MAX_TOKENS'),

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
    | Pooling Mode
    |--------------------------------------------------------------------------
    |
    | How to pool token embeddings into a single vector.
    | Options: mean, cls, last
    |
    */
    'pooling_mode' => env('NEURAPHP_POOLING_MODE', 'mean'),

    /*
|--------------------------------------------------------------------------
| Model Path (optional)
    |--------------------------------------------------------------------------
    |
    | Override the default model file path. If not set, the package will
    | look for models in bin/neuraphp/data/models/ relative to the project root.
    |
    */
    'model_path' => env('NEURAPHP_MODEL_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Library Path (optional)
    |--------------------------------------------------------------------------
    |
    | Override the default library path for libbert_shared.so.
    | If not set, the package will search bin/neuraphp/data/lib/ and system paths.
    |
    */
    'library_path' => env('NEURAPHP_LIBRARY_PATH'),
];
