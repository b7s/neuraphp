# Advanced Guide

This guide covers manual installation, the full API reference, configuration, and integration details not included in the main README.

---

## Manual Installation

If you prefer not to use the automatic installer, or it fails (e.g., Python not available for model conversion), you can set up everything manually.

### Step 1: Clone and compile embedding.cpp

```bash
git clone https://github.com/FFengIll/embedding.cpp
cd embedding.cpp
git submodule update --init --recursive
mkdir build && cd build
cmake .. -DCMAKE_BUILD_TYPE=Release -DCMAKE_POSITION_INDEPENDENT_CODE=ON
make -j$(nproc) bert_shared
```

This builds `libbert_shared.so` with static `libggml.a` baked in, so there is no runtime dependency on `libggml.so`.

### Step 2: Install the shared library

Copy the compiled library to a location where Neuraphp can find it:

```bash
# Option A: Project-local (recommended)
mkdir -p /path/to/your/project/bin/neuraphp-data/lib
cp libbert_shared.so /path/to/your/project/bin/neuraphp-data/lib/

# Option B: System-wide
sudo cp libbert_shared.so /usr/local/lib/libbert_shared.so
sudo ldconfig
```

Neuraphp searches for `libbert_shared.so` in this order:

1. Explicit path via `libraryPath()` or config
2. `<project_root>/bin/neuraphp-data/lib/libbert_shared.so`
3. `/usr/local/lib/libbert_shared.so`
4. `/usr/lib/libbert_shared.so`

### Step 3: Download and convert a model

You need a GGUF model file. The default is `all-MiniLM-L6-v2`:

```bash
cd embedding.cpp/models
git clone https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2

# Convert to GGUF format (requires Python + torch + transformers)
pip install torch numpy transformers
python3 convert-to-ggml.py all-MiniLM-L6-v2/ 0  # f32
python3 convert-to-ggml.py all-MiniLM-L6-v2/ 1  # f16

# Quantize (requires compiled quantize binary)
../build/bin/quantize all-MiniLM-L6-v2/ggml-model-f16.bin all-MiniLM-L6-v2/ggml-model-q4_0.bin 2
../build/bin/quantize all-MiniLM-L6-v2/ggml-model-f16.bin all-MiniLM-L6-v2/ggml-model-q4_1.bin 3
```

Place the model files in your project:

```bash
mkdir -p /path/to/your/project/bin/neuraphp-data/models/all-MiniLM-L6-v2/
cp all-MiniLM-L6-v2/ggml-model-q4_0.bin /path/to/your/project/bin/neuraphp-data/models/all-MiniLM-L6-v2/
```

### Step 4: Verify with the doctor command

```bash
./vendor/bin/neuraphp doctor
```

This checks FFI availability, library presence, model presence, and runs a test encoding.

---

## Python & virtualenv (for model conversion)

Model conversion requires Python with `torch`, `numpy`, and `transformers`. The automatic installer (`neuraphp install`) will:

1. Search for Python in project virtualenvs (`.venv/`, `venv/`, `env/`), home virtualenvs (`~/.venv/`, `~/myenv/`, `~/.virtualenvs/`), and system paths
2. If Python is found in a virtualenv and packages are missing, **auto-install** them into that virtualenv
3. If Python is not in a virtualenv and packages are missing, show clear instructions

Use `--python-path` to point to a specific Python:

```bash
# Create a virtualenv with the required packages
python3 -m venv ~/myenv
source ~/myenv/bin/activate
pip install torch numpy transformers

# Then install with that Python
./vendor/bin/neuraphp install --python-path=~/myenv/bin/python3
```

> **Disk space note:** The installer downloads and compiles in a temp directory, then copies only the final artifacts to your project. Model source files (~900 MB) and build artifacts are cleaned up automatically after conversion. Use `--keep-source` to preserve the original model files.

> **If automatic model conversion fails** (e.g., Python not available), the command will show clear manual instructions. The library compilation step does not require Python.

---

## Requirements

- **PHP 8.3+** with the FFI extension enabled
- `libbert_shared.so` compiled from embedding.cpp (see installation above)
- A GGUF model file

---

## Quick Start

```php
use B7s\Neuraphp\Neuraphp;
use B7s\Neuraphp\ModelReference;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;

// Single text embedding (uses default model: AllMiniLML6V2)
$result = Neuraphp::make()->embed('Hello world, this is a test sentence');
echo $result->vector();     // float[] — 384 floats
echo $result->dimension();  // 384
echo $result->model();      // 'sentence-transformers/all-MiniLM-L6-v2'
echo $result->duration();   // 0.012 (seconds)

// With explicit known model
$result = Neuraphp::make()
    ->model(ModelReference::fromEnum(Model::BgeSmallENV15))
    ->quantization(Quantization::F16)
    ->threads(4)
    ->embed('Hello world');

// With a custom HuggingFace model
$result = Neuraphp::make()
    ->model(ModelReference::fromId('custom-org/my-bert-model')->withDimensions(768))
    ->embed('Hello world');

// Batch embedding
$results = Neuraphp::make()
    ->model(ModelReference::fromEnum(Model::AllMiniLML12V2))
    ->embedBatch(['Hello world', 'Goodbye world']);

// Similarity search
$similarity = Neuraphp::make()
    ->cosineSimilarity('The cat sat on the mat', 'A feline rested on the rug');  // 0.87

// Custom paths
$result = Neuraphp::make()
    ->libraryPath('/custom/path/libbert_shared.so')
    ->modelPath('/custom/path/ggml-model-q4_0.bin')
    ->embed('Hello');
```

---

## API Reference

### Neuraphp Builder

| Method | Description | Returns |
|--------|-------------|---------|
| `make()` | Create new builder instance | `self` |
| `model(ModelReference)` | Set the model (default: `AllMiniLML6V2`) | `self` |
| `quantization(Quantization)` | Set quantization level | `self` |
| `threads(int)` | Set thread count for encoding | `self` |
| `poolingMode(PoolingMode)` | Set pooling strategy | `self` |
| `modelPath(string)` | Override model file path | `self` |
| `libraryPath(string)` | Override library path | `self` |
| `configPath(string)` | Override config file path | `self` |
| `embed(string)` | Embed single text (terminal) | `NeuraphpResult` |
| `embedBatch(string[])` | Embed multiple texts (terminal) | `NeuraphpResult[]` |
| `cosineSimilarity(string, string)` | Compare two texts (terminal) | `float` |
| `dimension()` | Get model dimension count | `int\|null` |
| `isAvailable()` | Check if FFI + library + model are ready | `bool` |

### ModelReference

`ModelReference` is an immutable value object that represents a model — either a known enum case or an arbitrary HuggingFace model.

| Method | Description | Returns |
|--------|-------------|---------|
| `fromEnum(Model)` | Create from a known Model enum case | `self` |
| `fromId(string)` | Create from a HuggingFace ID (auto-resolves known models) | `self` |
| `parse(string)` | Parse any string — short name, HF ID, or custom | `self` |
| `withDimensions(int)` | Return new instance with explicit dimensions | `self` |
| `withMaxTokens(int)` | Return new instance with explicit max tokens | `self` |
| `huggingFaceId()` | Full HuggingFace repo ID | `string` |
| `directoryName()` | Short directory name (repo name part) | `string` |
| `dimensions()` | Embedding dimensions (`null` for custom models) | `int\|null` |
| `maxTokens()` | Max token count (`null` for custom models) | `int\|null` |
| `isKnown()` | Whether this is a known Model enum case | `bool` |
| `toEnum()` | Get the backing Model enum (or `null`) | `Model\|null` |
| `displayName()` | Display-friendly identifier | `string` |
| `filename(Quantization)` | GGUF model filename for given quantization | `string` |
| `parseFromConfig(array)` | Create from config array with `model_dimensions`/`model_max_tokens` | `self\|null` |

```php
use B7s\Neuraphp\ModelReference;
use B7s\Neuraphp\Enums\Model;

// From a known enum
$ref = ModelReference::fromEnum(Model::BgeLargeENV15);
echo $ref->huggingFaceId();  // "BAAI/bge-large-en-v1.5"
echo $ref->dimensions();      // 1024

// From a HuggingFace ID (auto-resolves known models)
$ref = ModelReference::fromId('BAAI/bge-large-en-v1.5');
echo $ref->isKnown();  // true

// Custom model
$ref = ModelReference::fromId('custom-org/my-bert-model')
    ->withDimensions(768)
    ->withMaxTokens(512);
echo $ref->isKnown();     // false
echo $ref->dimensions();  // 768

// Parse any string
$ref = ModelReference::parse('bge-large-en-v1.5');
$ref = ModelReference::parse('BAAI/bge-large-en-v1.5');
$ref = ModelReference::parse('custom-org/my-model');
```

### NeuraphpResult

| Method | Description | Returns |
|--------|-------------|---------|
| `vector()` | The embedding vector | `float[]` |
| `dimension()` | Number of dimensions | `int` |
| `model()` | Model name used | `string` |
| `quantization()` | Quantization level used | `Quantization` |
| `duration()` | Time taken in seconds | `float` |
| `isSuccess()` | Whether embedding succeeded | `bool` |
| `toArray()` | Convert to array | `array` |
| `toJson()` | Convert to JSON | `string` |

### Vector Math

```php
use B7s\Neuraphp\Support\VectorMath;
use B7s\Neuraphp\Support\VectorNormalizer;

$similarity = VectorMath::cosineSimilarity($vectorA, $vectorB);
$dot        = VectorMath::dotProduct($vectorA, $vectorB);
$distance   = VectorMath::euclideanDistance($vectorA, $vectorB);
$magnitude  = VectorMath::magnitude($vector);
$normalized = VectorNormalizer::l2Normalize($vector);
```

---

## Supported Models

| Model | Enum Case | Dimensions | Max Tokens | HuggingFace ID |
|-------|-----------|-----------|------------|----------------|
| AllMiniLM-L6-v2 | `AllMiniLML6V2` | 384 | 512 | `sentence-transformers/all-MiniLM-L6-v2` |
| AllMiniLM-L12-v2 | `AllMiniLML12V2` | 384 | 512 | `sentence-transformers/all-MiniLM-L12-v2` |
| Paraphrase-MiniLM-L6-v2 | `ParaphraseMiniLML6V2` | 384 | 512 | `sentence-transformers/paraphrase-MiniLM-L6-v2` |
| Paraphrase-multilingual-MiniLM-L12-v2 | `ParaphraseMultilingualMiniLML12V2` | 384 | 512 | `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2` |
| BGE-small-en-v1.5 | `BgeSmallENV15` | 384 | 512 | `BAAI/bge-small-en-v1.5` |
| BGE-base-en-v1.5 | `BgeBaseENV15` | 768 | 512 | `BAAI/bge-base-en-v1.5` |
| BGE-large-en-v1.5 | `BgeLargeENV15` | 1024 | 512 | `BAAI/bge-large-en-v1.5` |
| BGE-small-zh-v1.5 | `BgeSmallZHV15` | 512 | 512 | `BAAI/bge-small-zh-v1.5` |
| BGE-base-zh-v1.5 | `BgeBaseZHV15` | 768 | 512 | `BAAI/bge-base-zh-v1.5` |
| BGE-large-zh-v1.5 | `BgeLargeZHV15` | 1024 | 512 | `BAAI/bge-large-zh-v1.5` |
| E5-small-v2 | `E5SmallV2` | 384 | 512 | `intfloat/e5-small-v2` |
| E5-base-v2 | `E5BaseV2` | 768 | 512 | `intfloat/e5-base-v2` |
| E5-large-v2 | `E5LargeV2` | 1024 | 512 | `intfloat/e5-large-v2` |
| Multilingual-E5-small | `MultilingualE5Small` | 384 | 512 | `intfloat/multilingual-e5-small` |
| Multilingual-E5-base | `MultilingualE5Base` | 768 | 512 | `intfloat/multilingual-e5-base` |

You can also use any BERT-compatible HuggingFace model via `ModelReference::fromId()`:

```php
$result = Neuraphp::make()
    ->model(ModelReference::fromId('my-org/my-bert-model')->withDimensions(512))
    ->embed('text');
```

---

## Quantization Levels

| Level | Enum | Description | Tradeoff |
|-------|------|-------------|----------|
| F32 | `Quantization::F32` | Full precision (32-bit float) | Best quality, largest files |
| F16 | `Quantization::F16` | Half precision (16-bit float) | Good quality, smaller files |
| Q4_0 | `Quantization::Q4_0` | 4-bit quantization (type 0) | Good quality/speed balance |
| Q4_1 | `Quantization::Q4_1` | 4-bit quantization (type 1) | Slightly better quality than Q4_0 |

Default: `Q4_0`

---

## Pooling Modes

| Mode | Enum | Description |
|------|------|-------------|
| Mean | `PoolingMode::Mean` | Average all token embeddings |
| CLS | `PoolingMode::CLS` | Use the [CLS] token embedding |
| Last | `PoolingMode::Last` | Use the last token embedding |

Default: `Mean`

---

## Configuration

Neuraphp uses cascading configuration: **explicit → project → package → defaults**.

### Config File

Create `config/neuraphp.php` or `.neuraphp.php` in your project root:

```php
return [
    'model' => 'all-MiniLM-L6-v2',
    'model_dimensions' => null,   // Only needed for custom models
    'model_max_tokens' => null,   // Only needed for custom models
    'quantization' => 'q4_0',
    'threads' => 4,
    'pooling_mode' => 'mean',
    'model_path' => null,   // Override model path
    'library_path' => null, // Override library path
];
```

The `model` field accepts:
- A known model short name (e.g. `'all-MiniLM-L6-v2'`, `'bge-large-en-v1.5'`)
- A full HuggingFace ID (e.g. `'BAAI/bge-large-en-v1.5'`, `'intfloat/e5-base-v2'`)
- A custom model ID (e.g. `'custom-org/my-bert-model'`)

For custom models, set `model_dimensions` and `model_max_tokens` so Neuraphp knows the model's capabilities.

### Environment Variables (Laravel)

```env
NEURAPHP_MODEL=all-MiniLM-L6-v2
NEURAPHP_MODEL_DIMENSIONS=
NEURAPHP_MODEL_MAX_TOKENS=
NEURAPHP_QUANTIZATION=q4_0
NEURAPHP_THREADS=4
NEURAPHP_POOLING_MODE=mean
NEURAPHP_MODEL_PATH=
NEURAPHP_LIBRARY_PATH=
```

---

## Laravel Integration

Add the service provider to `config/app.php`:

```php
'providers' => [
    B7s\Neuraphp\Laravel\NeuraphpServiceProvider::class,
],
```

Optionally add the facade:

```php
'aliases' => [
    'Neuraphp' => B7s\Neuraphp\Laravel\NeuraphpFacade::class,
],
```

Publish the config:

```bash
php artisan vendor:publish --tag=neuraphp-config
```

Usage:

```php
use Neuraphp;

// Facade
$result = Neuraphp::embed('Hello world');
$similarity = Neuraphp::cosineSimilarity('cat', 'dog');
```

---

## CLI Commands Reference

### `neuraphp install`

Auto-install library and model.

```bash
./vendor/bin/neuraphp install
```

| Option | Description |
|--------|-------------|
| `--model=NAME` | Model short name or full HuggingFace ID |
| `--quantization=LEVEL` | Quantization level (f32, f16, q4_0, q4_1) |
| `--skip-library` | Skip library compilation |
| `--skip-model` | Skip model download |
| `--force` | Force re-download/re-compile |
| `--keep-source` | Keep model source files after conversion |
| `--python-path=PATH` | Use a specific Python for model conversion |

### `neuraphp doctor`

Check if Neuraphp is properly configured (FFI, library, model, test encoding).

```bash
./vendor/bin/neuraphp doctor
./vendor/bin/neuraphp doctor --library-path=/custom/libbert_shared.so
```

### `neuraphp info`

Show model and configuration info.

```bash
./vendor/bin/neuraphp info
./vendor/bin/neuraphp info --model=all-MiniLM-L6-v2 --quantization=q4_0
```

---

## Config Resolution Order

1. **Explicit** — Values set via builder methods (`->model()`, `->threads()`, etc.)
2. **Project config** — `config/neuraphp.php` or `.neuraphp.php` in project root
3. **Package defaults** — `stubs/neuraphp-config.php`
4. **Hardcoded defaults** — `Model::default()`, `Quantization::default()`, 4 threads, Mean pooling
