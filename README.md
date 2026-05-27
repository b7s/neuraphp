<div align="center">
   <img src="docs/logo.png" width="222" alt="NeuraPHP">

   # NeuraPHP

Local text embeddings via PHP FFI, powered by embedding.cpp. No Python, no API calls, no external services at runtime.

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
</div>

## Features

- **Local embeddings** — No API calls, no network latency, no data leaving your server
- **PHP FFI** — Direct memory access to a **C library**, no separate process
- **Fluent API** — `Neuraphp::make()->model(ModelReference::fromEnum(Model::AllMiniLML12V2))->embed('text')`
- **Multiple models** — AllMiniLM-L6-v2, AllMiniLM-L12-v2, Paraphrase, BGE, E5, multilingual; or any BERT-compatible HuggingFace model
- **Quantization** — F32, F16, Q4_0, Q4_1 for speed/quality tradeoffs
- **Vector math** — Cosine similarity, dot product, Euclidean distance, L2 normalization
- **CLI tools** — `neuraphp install` for auto-setup, `neuraphp doctor` for diagnostics, `neuraphp info` for configuration
- **Laravel integration** — Optional service provider and facade
- **Framework-agnostic** — Works with any PHP 8.3+ project


### Add to your project:
```bash
composer require b7s/neuraphp
```

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
2. **Clone embedding.cpp** into a temp directory and compile `libbert_shared.so`
3. **Download the default model** (all-MiniLM-L6-v2) from HuggingFace
4. **Convert the model** to GGUF format (requires Python + torch + transformers)
5. **Copy only final artifacts** to `bin/neuraphp/` in your project root
   - **Clean up** temp files and create `bin/neuraphp/.gitignore` so artifacts are never committed

**Options:**

```bash
# Install a specific model (short name or full HuggingFace ID)
./vendor/bin/neuraphp install --model=bge-small-en-v1.5 --quantization=f16

# Install a custom BERT-compatible model
./vendor/bin/neuraphp install --model=custom-org/my-bert-model

# Skip library compilation (if already installed)
./vendor/bin/neuraphp install --skip-library

# Skip model download (if already downloaded)
./vendor/bin/neuraphp install --skip-model

# Force re-download/re-compile
./vendor/bin/neuraphp install --force

# Keep model source files after conversion
./vendor/bin/neuraphp install --keep-source

# Use a specific Python for model conversion
./vendor/bin/neuraphp install --python-path=~/myenv/bin/python3

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

---

Logo by: 
<a href="https://www.flaticon.com/free-icons/neuro" title="neuro icons">Neuro icons created by Uniconlabs - Flaticon</a>
