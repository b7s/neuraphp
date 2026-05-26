# AGENTS.md — Neuraphp Developer Guide

## Architecture Overview

Neuraphp is a standalone PHP package providing local text embeddings via PHP FFI, powered by `embedding.cpp` (GGUF-based BERT models). No Python, no API calls, no external services at runtime.

**Package:** `b7s/neuraphp`
**Namespace:** `B7s\Neuraphp`
**Target:** PHP 8.3+ (framework-agnostic, Laravel-optional)

## Key Design Principles

1. **Fluent builder pattern** — `Neuraphp::make()->model()->embed()` is the primary API
2. **Immutable config** — `Config` uses `with*()` methods returning new instances
3. **Lazy FFI init** — The FFI bridge loads on first `embed()` call, not at construction
4. **Framework-agnostic core** — No Laravel dependency in the main package
5. **Strict typing** — `declare(strict_types=1)` everywhere, enums for all status values
6. **Guard clauses** — Validate early, throw typed exceptions immediately

## File Map

| File | Purpose |
|------|---------|
| `src/Neuraphp.php` | Fluent builder — the main entry point |
| `src/Config.php` | Cascading config: explicit → project → package → defaults |
| `src/NeuraphpResult.php` | Immutable result object (readonly class) |
| `src/NeuraphpService.php` | FFI bridge to `libbert_shared.so` |
| `src/Enums/Model.php` | Predefined models with dimensions and max tokens |
| `src/Enums/Quantization.php` | F32, F16, Q4_0, Q4_1 |
| `src/Enums/PoolingMode.php` | Mean, CLS, Last |
| `src/Exceptions/*.php` | Typed exceptions: NeuraphpException, LibraryNotFound, ModelNotFound, FFI |
| `src/Support/VectorMath.php` | Cosine similarity, dot product, Euclidean distance, magnitude |
| `src/Support/VectorNormalizer.php` | L2 normalization |
| `src/Console/Application.php` | Symfony Console app |
| `src/Console/Commands/DoctorCommand.php` | Diagnostics: FFI, library, model, test encoding |
| `src/Console/Commands/InfoCommand.php` | Show model info and configuration |
| `src/Console/Commands/InstallCommand.php` | Auto-install embedding.cpp library and models |
| `src/Laravel/NeuraphpServiceProvider.php` | Laravel service provider (optional) |
| `src/Laravel/NeuraphpFacade.php` | Laravel facade (optional) |

## Testing

- **Framework:** Pest 4.x
- **Command:** `composer test` or `vendor/bin/pest --parallel`
- **Location:** `tests/Unit/`
- **Pattern:** `describe`/`it` blocks per class

### Test Categories

| Test File | What It Tests |
|-----------|---------------|
| `ModelTest.php` | Enum values, dimensions, filenames, directory names |
| `QuantizationTest.php` | Enum values, suffixes, filenames, labels |
| `PoolingModeTest.php` | Enum values, descriptions |
| `ExceptionsTest.php` | Factory methods, message formatting, inheritance |
| `ConfigTest.php` | Defaults, immutability, cascading, path resolution |
| `NeuraphpResultTest.php` | Factory, accessors, immutability, serialization |
| `VectorMathTest.php` | Cosine similarity, dot product, Euclidean distance, magnitude |
| `VectorNormalizerTest.php` | L2 normalization, zero vectors, edge cases |
| `NeuraphpTest.php` | Fluent builder, defaults, validation, dimension |

### Running Quality Checks

```bash
composer pint          # Code style (Laravel Pint)
composer stan          # Static analysis (PHPStan level max)
composer test          # Run tests (Pest --parallel)
composer catraca       # Quality gate (catraca)
composer check         # All of the above
```

## Config Resolution Order

1. **Explicit** — Values set via builder methods (`->model()`, `->threads()`, etc.)
2. **Project config** — `config/neuraphp.php` or `.neuraphp.php` in project root
3. **Package defaults** — `stubs/neuraphp-config.php`
4. **Hardcoded defaults** — `Model::AllMiniLML6V2`, `Quantization::Q4_0`, 4 threads

## FFI Bridge Notes

- The `NeuraphpService` class bridges PHP to `libbert_shared.so` via PHP FFI
- FFI is only available at runtime — PHPStan can't analyze it (excluded from analysis)
- The service uses lazy initialization — FFI loads on first `embed()` call
- `__destruct()` frees the bert context to prevent memory leaks
- `NeuraphpResult` holds a PHP `float[]`, not an FFI pointer — safe to keep around

## Default Model Behavior

If `model()` is not called on the builder, `Model::AllMiniLML6V2` is used as the default. This means:

```php
// These are equivalent:
Neuraphp::make()->embed('text');
Neuraphp::make()->model(Model::AllMiniLML6V2)->embed('text');
```

## Release Checklist

1. Run `composer check` — all gates must pass
2. Verify `catraca_baseline.json` is up to date
3. Update `README.md` version if needed
4. Tag release in git