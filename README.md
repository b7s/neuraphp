# Neuraphp

Local text embeddings via PHP FFI, powered by embedding.cpp. No Python, no API calls, no external services at runtime.

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Features

- **Local embeddings** — No API calls, no network latency, no data leaving your server
- **PHP FFI** — Direct memory access to a **C library**, no separate process
- **Fluent API** — `Neuraphp::make()->model(Model::AllMiniLML12V2)->embed('text')`
- **Multiple models** — AllMiniLM-L6-v2, AllMiniLM-L12-v2, Paraphrase, BGE models
- **Quantization** — F32, F16, Q4_0, Q4_1 for speed/quality tradeoffs
- **Vector math** — Cosine similarity, dot product, Euclidean distance, L2 normalization
- **CLI tools** — `neuraphp install` for auto-setup, `neuraphp doctor` for diagnostics, `neuraphp info` for configuration
- **Laravel integration** — Optional service provider and facade
- **Framework-agnostic** — Works with any PHP 8.3+ project

## ⚠️ Prerequisites: embedding.cpp Library & Model

**Neuraphp requires `libbert_shared.so` (compiled from embedding.cpp) and a GGUF model file to function.** There are two ways to set these up:

---

### Option A: Automatic Installation (Recommended)

Run the installation command - it clones, compiles, and downloads everything for you:

```bash
./vendor/bin/neuraphp install
```

This will:
1. **Check prerequisites** (git, cmake, make, C++ compiler, Rust, git-lfs)
2. **Clone embedding.cpp** and compile `libbert_shared.so`
3. **Download the default model** (all-MiniLM-L6-v2) from HuggingFace
4. **Convert the model** to GGUF format (requires Python + torch + transformers)
5. **Place everything** in the right directories

**Options:**

```bash
# Install a specific model
./vendor/bin/neuraphp install --model=all-MiniLM-L6-v2 --quantization=q4_0

# Skip library compilation (if already installed)
./vendor/bin/neuraphp install --skip-library

# Skip model download (if already downloaded)
./vendor/bin/neuraphp install --skip-model

# Force re-download/re-compile
./vendor/bin/neuraphp install --force
```

**If automatic model conversion fails** (e.g., Python not available), the command will show clear manual instructions. The library compilation step does not require Python.

---

### Option B: Manual Installation

#### Step 1: Clone and compile embedding.cpp

```bash
git clone https://github.com/FFengIll/embedding.cpp
cd embedding.cpp
git submodule update --init --recursive
mkdir build && cd build
cmake .. -DCMAKE_BUILD_TYPE=Release -DBUILD_SHARED_LIBS=ON
make -j$(nproc) bert
```

> **Note:** Use `-DBUILD_SHARED_LIBS=ON` to build the shared library. The output will be `libbert.so` (or `libbert_shared.so` depending on your platform).

#### Step 2: Install the shared library

Copy the compiled library to a location where Neuraphp can find it:

```bash
# Option A: Project-local (recommended)
mkdir -p /path/to/your/project/lib
cp libbert*.so /path/to/your/project/lib/libbert_shared.so

# Option B: System-wide
sudo cp libbert*.so /usr/local/lib/libbert_shared.so
sudo ldconfig
```

Neuraphp searches for `libbert_shared.so` in this order:
1. Explicit path via `libraryPath()` or config
2. `<package_root>/lib/libbert_shared.so`
3. `/usr/local/lib/libbert_shared.so`
4. `/usr/lib/libbert_shared.so`

#### Step 3: Download and convert a model

You need a GGUF model file. The default is `all-MiniLM-L6-v2`:

```bash
cd embedding.cpp/models
git clone https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2

# Convert to GGUF format (requires Python + torch + transformers)
pip install torch numpy transformers
python3 convert-to-ggml.py all-MiniLM-L6-v2/ 0   # f32
python3 convert-to-ggml.py all-MiniLM-L6-v2/ 1   # f16

# Quantize (requires compiled quantize binary)
../build/bin/quantize all-MiniLM-L6-v2/ggml-model-f16.bin all-MiniLM-L6-v2/ggml-model-q4_0.bin 2
../build/bin/quantize all-MiniLM-L6-v2/ggml-model-f16.bin all-MiniLM-L6-v2/ggml-model-q4_1.bin 3
```

Place the model files in your project:

```bash
mkdir -p /path/to/your/project/models/all-MiniLM-L6-v2/
cp all-MiniLM-L6-v2/ggml-model-q4_0.bin /path/to/your/project/models/all-MiniLM-L6-v2/
```

#### Step 4: Verify with the doctor command

```bash
./vendor/bin/neuraphp doctor
```

This checks FFI availability, library presence, model presence, and runs a test encoding.

## Installation

```bash
composer require b7s/neuraphp
```

**Requirements:**
- PHP 8.3+ with the FFI extension enabled
- `libbert_shared.so` compiled from embedding.cpp (see above)
- A GGUF model file

## Quick Start

```php
use B7s\Neuraphp\Neuraphp;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;

// Single text embedding (uses default model: AllMiniLML6V2)
$result = Neuraphp::make()->embed('Hello world, this is a test sentence');

echo $result->vector();      // float[] — 384 floats
echo $result->dimension();   // 384
echo $result->model();       // 'all-MiniLM-L6-v2'
echo $result->duration();    // 0.012 (seconds)

// With explicit model and quantization
$result = Neuraphp::make()
    ->model(Model::BgeSmallENV15)
    ->quantization(Quantization::F16)
    ->threads(4)
    ->embed('Hello world');

// Batch embedding
$results = Neuraphp::make()
    ->model(Model::AllMiniLML12V2)
    ->embedBatch(['Hello world', 'Goodbye world']);

// Similarity search
$similarity = Neuraphp::make()
    ->cosineSimilarity('The cat sat on the mat', 'A feline rested on the rug');
// 0.87

// Custom paths
$result = Neuraphp::make()
    ->libraryPath('/custom/path/libbert_shared.so')
    ->modelPath('/custom/path/ggml-model-q4_0.bin')
    ->embed('Hello');
```

## API Reference

### Neuraphp Builder

| Method | Description | Returns |
|--------|-------------|---------|
| `make()` | Create new builder instance | `self` |
| `model(Model)` | Set the model (default: `AllMiniLML6V2`) | `self` |
| `quantization(Quantization)` | Set quantization level | `self` |
| `threads(int)` | Set thread count for encoding | `self` |
| `modelPath(string)` | Override model file path | `self` |
| `libraryPath(string)` | Override library path | `self` |
| `configPath(string)` | Override config file path | `self` |
| `embed(string)` | Embed single text (terminal) | `NeuraphpResult` |
| `embedBatch(string[])` | Embed multiple texts (terminal) | `NeuraphpResult[]` |
| `cosineSimilarity(string, string)` | Compare two texts (terminal) | `float` |
| `dimension()` | Get model dimension count | `int` |
| `isAvailable()` | Check if FFI + library + model are ready | `bool` |

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
$dot = VectorMath::dotProduct($vectorA, $vectorB);
$distance = VectorMath::euclideanDistance($vectorA, $vectorB);
$magnitude = VectorMath::magnitude($vector);
$normalized = VectorNormalizer::l2Normalize($vector);
```

### Supported Models

| Model | Dimensions | Max Tokens |
|-------|-----------|------------|
| `AllMiniLML6V2` | 384 | 512 |
| `AllMiniLML12V2` | 384 | 512 |
| `ParaphraseMiniLML6V2` | 384 | 512 |
| `ParaphraseMultilingualMiniLML12V2` | 384 | 512 |
| `BgeSmallENV15` | 384 | 512 |
| `BgeBaseENV15` | 768 | 512 |

### Quantization Levels

| Level | Description | Tradeoff |
|-------|-------------|----------|
| `F32` | Full precision (32-bit) | Best quality, largest files |
| `F16` | Half precision (16-bit) | Good quality, smaller files |
| `Q4_0` | 4-bit quantization (type 0) | Good quality/speed balance |
| `Q4_1` | 4-bit quantization (type 1) | Slightly better quality than Q4_0 |

## Configuration

Neuraphp uses cascading configuration: **explicit → project → package → defaults**.

### Config File

Create `config/neuraphp.php` or `.neuraphp.php` in your project root:

```php
return [
    'model' => 'all-MiniLM-L6-v2',
    'quantization' => 'q4_0',
    'threads' => 4,
    'pooling_mode' => 'mean',
    'model_path' => null,    // Override model path
    'library_path' => null,  // Override library path
];
```

### Environment Variables (Laravel)

```env
NEURAPHP_MODEL=all-MiniLM-L6-v2
NEURAPHP_QUANTIZATION=q4_0
NEURAPHP_THREADS=4
NEURAPHP_MODEL_PATH=
NEURAPHP_LIBRARY_PATH=
```

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
use Neuraphp; // Facade

$result = Neuraphp::embed('Hello world');
$similarity = Neuraphp::cosineSimilarity('cat', 'dog');
```

## CLI Commands

```bash
# Auto-install library and model (recommended first step)
./vendor/bin/neuraphp install

# Install a specific model
./vendor/bin/neuraphp install --model=bge-small-en-v1.5 --quantization=f16

# Skip library compilation (if already installed)
./vendor/bin/neuraphp install --skip-library

# Skip model download (if already downloaded)
./vendor/bin/neuraphp install --skip-model

# Force re-download/re-compile
./vendor/bin/neuraphp install --force

# Check if Neuraphp is properly configured
./vendor/bin/neuraphp doctor

# Show model and configuration info
./vendor/bin/neuraphp info

# With options
./vendor/bin/neuraphp doctor --library-path=/custom/libbert_shared.so
./vendor/bin/neuraphp info --model=all-MiniLM-L6-v2 --quantization=q4_0
```

## Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Code style
composer pint

# Static analysis
composer stan

# Quality gate
composer catraca

# Run all checks
composer check
```

## License

MIT