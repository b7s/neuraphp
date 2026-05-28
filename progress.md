# Neuraphp — Progress Tracker

## Overview

A standalone PHP package providing local text embeddings via PHP FFI, powered by `embedding.cpp` (GGUF-based BERT models). No Python, no API calls, no external services at runtime. Fluent API inspired by [b7s/fluentcut](https://github.com/b7s/fluentcut).

**Package name:** `b7s/neuraphp`  
**Namespace:** `B7s\Neuraphp`  
**Target:** Any PHP 8.3+ project (framework-agnostic, Laravel-optional)

---

## Architecture

```
neuraphp/
├── bin/neuraphp/cli # CLI entrypoint (doctor, info)
├── src/
│   ├── Neuraphp.php                 # Main fluent builder (terminal: embed())
│   ├── Config.php                   # Config loader (cascading: explicit → project → package → defaults)
│   ├── NeuraphpResult.php           # Result object (vectors, dim, model, timing)
│   ├── NeuraphpService.php          # Core FFI bridge to libbert_shared.so
│   ├── Console/
│   │   └── Application.php          # Symfony Console app
│   │   └── Commands/
│   │       ├── DoctorCommand.php    # Check FFI, library, model
│   │       └── InfoCommand.php      # Show model info, dimensions
│   ├── Enums/
│   │   ├── Model.php               # Predefined models (AllMiniLML6V2, etc.)
│   │   ├── Quantization.php        # F32, F16, Q4_0, Q4_1
│   │   └── PoolingMode.php         # Mean, CLS, Last
│   ├── Exceptions/
│   │   ├── NeuraphpException.php   # Base exception
│   │   ├── LibraryNotFoundException.php
│   │   ├── ModelNotFoundException.php
│   │   └── FFIException.php
│   └── Support/
│       ├── VectorMath.php          # Cosine similarity, dot product, euclidean distance
│       └── VectorNormalizer.php    # L2 normalization
├── tests/
│   ├── Pest.php
│   ├── TestCase.php
│   └── Unit/
│       ├── NeuraphpTest.php        # Fluent builder tests
│       ├── NeuraphpResultTest.php
│       ├── VectorMathTest.php
│       ├── ConfigTest.php
│       └── ModelTest.php
├── stubs/
│   └── neuraphp-config.php         # Default config template
├── catraca_baseline.json           # Quality gate baseline
├── phpstan.neon                    # Level max
├── phpunit.xml                     # Pest bootstrap
├── Makefile                        # Dev workflow shortcuts
├── composer.json
├── AGENTS.md                       # Agent developer guide
└── README.md                       # Comprehensive docs (after implementation)
```

---

## C Library: embedding.cpp

### Source
- **Repo:** https://github.com/FFengIll/embedding.cpp
- **Build target:** `libbert_shared.so` (shared library for PHP FFI)
- **Model format:** GGUF (converted from HuggingFace sentence-transformers)
- **Default model:** `all-MiniLM-L6-v2` (384 dimensions, ~14MB quantized)

### C API (from bert.h)

```c
struct bert_ctx;

struct bert_ctx *bert_load_from_file(const char *fname);
void bert_free(struct bert_ctx *ctx);
int32_t bert_n_embd(struct bert_ctx *ctx);
int32_t bert_n_max_tokens(struct bert_ctx *ctx);
void bert_encode(struct bert_ctx *ctx, int32_t n_threads, const char *texts, float *embeddings);
void bert_encode_batch(struct bert_ctx *ctx, int32_t n_threads, int32_t n_batch_size, int32_t n_inputs, const char **texts, float **embeddings);
void bert_tokenize(struct bert_ctx *ctx, const char *text, bert_vocab_id *tokens, int32_t *n_tokens, int32_t n_max_tokens);
void bert_eval(struct bert_ctx *ctx, int32_t n_threads, bert_vocab_id *tokens, int32_t n_tokens, float *embeddings);
```

### Build Steps

```bash
git clone https://github.com/FFengIll/embedding.cpp
cd embedding.cpp
git submodule update --init --recursive
mkdir build && cd build
cmake .. -DCMAKE_BUILD_TYPE=Release
make bert_shared    # Produces libbert_shared.so
```

### Model Conversion (one-time, requires Python)

```bash
cd embedding.cpp/models
# Download model from HuggingFace
git clone https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2
# Convert to GGUF formats
sh run_conversions.sh all-MiniLM-L6-v2
# Produces: ggml-model-f32.bin, ggml-model-f16.bin, ggml-model-q4_0.bin, ggml-model-q4_1.bin
```

### PHP FFI Bridge

```php
// FFI\cdef maps bert_* functions to PHP
$ffi = FFI::cdef("
    typedef struct bert_ctx bert_ctx;
    bert_ctx* bert_load_from_file(const char* fname);
    void bert_free(bert_ctx* ctx);
    int32_t bert_n_embd(bert_ctx* ctx);
    int32_t bert_n_max_tokens(bert_ctx* ctx);
    void bert_encode(bert_ctx* ctx, int32_t n_threads, const char* texts, float* embeddings);
    void bert_free_float(float* ptr);
", 'libbert_shared.so');
```

---

## Fluent API Design

### Usage Examples

```php
use B7s\Neuraphp\Neuraphp;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;

// Single text embedding
$result = Neuraphp::make()
    ->model(Model::AllMiniLML6V2)
    ->quantization(Quantization::default())
    ->threads(4)
    ->embed('Hello world, this is a test sentence');

$result->vector();       // float[] — 384 floats
$result->dimension();    // 384
$result->model();        // 'all-MiniLM-L6-v2'
$result->duration();     // 0.012 (seconds)

// Batch embedding
$results = Neuraphp::make()
    ->model(Model::AllMiniLML6V2)
    ->embedBatch(['Hello world', 'Goodbye world']);

$results[0]->vector();  // float[]
$results[1]->vector();  // float[]

// Similarity search
$similarity = Neuraphp::make()
    ->model(Model::AllMiniLML6V2)
    ->cosineSimilarity('The cat sat on the mat', 'A feline rested on the rug');
// 0.87

// With explicit config
$result = Neuraphp::make()
    ->configPath('/etc/neuraphp/config.php')
    ->embed('Hello');

// Laravel integration (optional)
$result = Neuraphp::make()->embed('Hello');
// Or via facade after registering service provider
$result = NeuraphpFacade::embed('Hello');
```

### Builder Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `make()` | Create new builder instance | `self` |
| `model(Model)` | Set the model to use | `self` |
| `quantization(Quantization)` | Set quantization level | `self` |
| `threads(int)` | Set thread count for encoding | `self` |
| `modelPath(string)` | Override model file path | `self` |
| `libraryPath(string)` | Override .so library path | `self` |
| `configPath(string)` | Override config file path | `self` |
| `embed(string)` | Embed single text (terminal) | `NeuraphpResult` |
| `embedBatch(string[])` | Embed multiple texts (terminal) | `NeuraphpResult[]` |
| `cosineSimilarity(string, string)` | Compare two texts (terminal) | `float` |
| `dimension()` | Get model dimension count | `int` |
| `isAvailable()` | Check if FFI + library + model are ready | `bool` |

---

## Step-by-Step Implementation Plan

### Phase 1: Project Scaffolding

- [x] **1.1** Create `composer.json` with all requirements and dev requirements
- [x] **1.2** Create `phpunit.xml` for Pest
- [x] **1.3** Create `phpstan.neon` (level max)
- [x] **1.4** Create `catraca_baseline.json`
- [x] **1.5** Create `Makefile` with dev workflow shortcuts
- [x] **1.6** Create `tests/Pest.php` and `tests/TestCase.php`
- [x] **1.7** Create `.gitignore`
- [x] **1.8** Run `composer install` and verify toolchain works

### Phase 2: Core Enums and Value Objects

- [x] **2.1** Create `src/Enums/Model.php` — predefined models with name, dimensions, max tokens
- [x] **2.2** Create `src/Enums/Quantization.php` — F32, F16, Q4_0, Q4_1 with file suffixes
- [x] **2.3** Create `src/Enums/PoolingMode.php` — Mean, CLS, Last
- [x] **2.4** Create `src/Exceptions/NeuraphpException.php` — base exception
- [x] **2.5** Create `src/Exceptions/LibraryNotFoundException.php`
- [x] **2.6** Create `src/Exceptions/ModelNotFoundException.php`
- [x] **2.7** Create `src/Exceptions/FFIException.php`
- [x] **2.8** Write unit tests for all enums and exceptions

### Phase 3: Config System

- [x] **3.1** Create `src/Config.php` — cascading config loader (explicit → project → package → defaults)
- [x] **3.2** Create `stubs/neuraphp-config.php` — default config template
- [x] **3.3** Write unit tests for Config

### Phase 4: FFI Bridge (Core)

- [x] **4.1** Create `src/NeuraphpService.php` — FFI bridge to `libbert_shared.so`
  - Load shared library via `FFI::cdef()`
  - Initialize model context (`bert_load_from_file`)
  - Encode single text (`bert_encode`)
  - Encode batch (`bert_encode_batch` when available)
  - Free context (`bert_free`)
  - Get model info (`bert_n_embd`, `bert_n_max_tokens`)
  - Handle FFI errors gracefully
  - Lazy initialization (load on first use)
  - Thread-safe singleton pattern
- [x] **4.2** Write unit tests for NeuraphpService (mock FFI where needed)

### Phase 5: Result Object

- [x] **5.1** Create `src/NeuraphpResult.php` — immutable result object
  - `vector(): float[]`
  - `dimension(): int`
  - `model(): string`
  - `quantization(): Quantization`
  - `duration(): float`
  - `toArray(): array`
  - `toJson(): string`
  - `isSuccess(): bool`
- [x] **5.2** Write unit tests for NeuraphpResult

### Phase 6: Vector Math Utilities

- [x] **6.1** Create `src/Support/VectorMath.php`
  - `cosineSimilarity(float[] $a, float[] $b): float`
  - `dotProduct(float[] $a, float[] $b): float`
  - `euclideanDistance(float[] $a, float[] $b): float`
  - `magnitude(float[] $v): float`
- [x] **6.2** Create `src/Support/VectorNormalizer.php`
  - `l2Normalize(float[] $v): float[]`
- [x] **6.3** Write unit tests for VectorMath and VectorNormalizer

### Phase 7: Fluent Builder

- [x] **7.1** Create `src/Neuraphp.php` — main fluent builder
  - `make(): self` — static factory
  - `model(Model): self`
  - `quantization(Quantization): self`
  - `threads(int): self`
  - `modelPath(string): self`
  - `libraryPath(string): self`
  - `configPath(string): self`
  - `embed(string): NeuraphpResult` — terminal operation
  - `embedBatch(string[]): NeuraphpResult[]` — terminal operation
  - `cosineSimilarity(string, string): float` — terminal operation
  - `dimension(): int`
  - `isAvailable(): bool`
- [x] **7.2** Write comprehensive unit tests for Neuraphp builder
  - Test fluent chaining
  - Test default values
  - Test terminal operations (with mocked NeuraphpService)
  - Test validation (empty text, missing model, etc.)

### Phase 8: CLI Commands

- [x] **8.1** Create `bin/neuraphp/cli` — CLI entrypoint
- [x] **8.2** Create `src/Console/Application.php`
- [x] **8.3** Create `src/Console/Commands/DoctorCommand.php`
  - Check PHP FFI extension loaded
  - Check `libbert_shared.so` found and loadable
  - Check model file exists
  - Test encode "Hello world" and verify dimensions
- [x] **8.4** Create `src/Console/Commands/InfoCommand.php`
  - Show model name, dimensions, max tokens
  - Show library path, model path
  - Show quantization level
- [x] **8.5** Create `src/Console/Commands/InstallCommand.php`
  - Auto-clone and compile embedding.cpp
  - Auto-download models from HuggingFace
  - Auto-convert models to GGUF format (requires Python)
  - Graceful fallback with manual instructions on failure
- [x] **8.6** Write tests for CLI commands

### Phase 9: Laravel Integration (Optional)

- [x] **9.1** Create `src/Laravel/NeuraphpServiceProvider.php`
  - Register singleton for NeuraphpService
  - Publish config file
  - Register facade
- [x] **9.2** Create `src/Laravel/NeuraphpFacade.php`
  - `embed(string): NeuraphpResult`
  - `embedBatch(string[]): NeuraphpResult[]`
  - `cosineSimilarity(string, string): float`
  - `isAvailable(): bool`
- [x] **9.3** Create `config/neuraphp.php` — Laravel config template
- [x] **9.4** Write tests for Laravel integration

### Phase 10: Quality Gate

- [x] **10.1** Run `vendor/bin/pint --dirty` and fix all style issues
- [x] **10.2** Run `phpstan analyse src --level=max` and fix all errors
- [x] **10.3** Run `vendor/bin/pest --parallel` and ensure all tests pass
- [x] **10.4** Run `vendor/bin/catraca` and fix any quality issues
- [x] **10.5** Update `catraca_baseline.json` with final metrics

### Phase 11: Documentation

- [x] **11.1** Write `README.md` (badged header, features, installation, usage, API reference, CLI, Laravel integration)
- [x] **11.2** Write `AGENTS.md` (architecture, testing, config resolution, release workflow)

---

## Key Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **FFI over HTTP** | PHP FFI | No separate process, no network overhead, direct memory access |
| **embedding.cpp over llama.cpp** | embedding.cpp | Simpler C API, purpose-built for embeddings, smaller binary |
| **Fluent builder** | `Neuraphp::make()->model()->embed()` | Matches fluentcut pattern, discoverable API |
| **Result object** | `NeuraphpResult` with `vector()`, `dimension()`, etc. | Structured, testable, multiple output formats |
| **No Laravel dependency** | Standalone library | Matches fluentcut approach, Laravel integration is optional |
| **Config cascading** | Explicit → project → package → defaults | Same pattern as fluentcut, flexible for all environments |
| **Lazy FFI init** | Load on first `embed()` call | Avoid FFI errors at construction time, allow `isAvailable()` checks |
| **Model enum** | Predefined models with metadata | Type-safe, IDE autocompletion, self-documenting |

---

## Dependencies

### Runtime
- `php`: `^8.3` (FFI support required)
- `ext-ffi`: required
- `symfony/console`: `^7.0|^8.0` (CLI commands only)

### Dev
- `b7s/catraca`: `^1.0`
- `laravel/pint`: `^1.29`
- `pestphp/pest`: `^4.3`
- `phpstan/phpstan`: `^2.0`
- `systemsdk/phpcpd`: `^8.3`

### External (not in composer)
- `libbert_shared.so` — compiled from embedding.cpp
- GGUF model file (e.g., `ggml-model-q4_0.bin`)

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| FFI not available | `DoctorCommand` checks, `isAvailable()` method, clear error messages |
| `libbert_shared.so` not found | Configurable path, cascading search, `LibraryNotFoundException` |
| Model file not found | Configurable path, `ModelNotFoundException` |
| Thread safety | Singleton pattern with lazy init, FFI context per process |
| Memory leaks | `__destruct()` frees bert context, `NeuraphpResult` holds PHP array (not FFI pointer) |
| Batch encoding not ready in embedding.cpp | Fallback to sequential `bert_encode` calls |
| Different dimensions across models | `Model` enum stores expected dimensions, validated at runtime via `bert_n_embd()` |