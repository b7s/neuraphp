<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Console\Commands;

use B7s\Neuraphp\Config;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\ModelReference;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'install',
    description: 'Install embedding.cpp library and download models automatically',
)]
final class InstallCommand extends Command
{
    private string $projectRoot;

    public function __construct()
    {
        parent::__construct();
        $this->projectRoot = Config::resolveProjectRoot();
    }

    protected function configure(): void
    {
        $this->addOption('model', null, InputOption::VALUE_OPTIONAL, 'Model to download (enum name or HuggingFace ID like BAAI/bge-large-en-v1.5)', Model::default()->value);
        $this->addOption('quantization', null, InputOption::VALUE_OPTIONAL, 'Quantization level', Quantization::default()->value);
        $this->addOption('skip-library', null, InputOption::VALUE_NONE, 'Skip library compilation');
        $this->addOption('skip-model', null, InputOption::VALUE_NONE, 'Skip model download');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Force re-download/re-compile even if files exist');
        $this->addOption('keep-source', null, InputOption::VALUE_NONE, 'Keep downloaded model source files after conversion');
        $this->addOption('python-path', null, InputOption::VALUE_OPTIONAL, 'Path to Python executable (e.g. ~/myenv/bin/python3)', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Neuraphp Installer');

        /** @var string $modelValue */
        $modelValue = $input->getOption('model');
        $model = ModelReference::parse($modelValue);

        /** @var string $quantizationValue */
        $quantizationValue = $input->getOption('quantization');
        $quantization = Quantization::from($quantizationValue);

        /** @var bool $skipLibrary */
        $skipLibrary = $input->getOption('skip-library');
        /** @var bool $skipModel */
        $skipModel = $input->getOption('skip-model');
        /** @var bool $force */
        $force = $input->getOption('force');

        /** @var bool $keepSource */
        $keepSource = $input->getOption('keep-source');

        /** @var string $pythonPath */
        $pythonPath = $input->getOption('python-path');

        // Create a temp directory for all build/download operations
        $tempDir = $this->createTempDir();

        $hasErrors = false;

        try {
            // Step 1: Install library
            if (! $skipLibrary) {
                if (! $this->installLibrary($io, $force, $tempDir)) {
                    $hasErrors = true;
                }
            } else {
                $io->note('Skipping library installation (--skip-library).');
            }

            // Step 2: Download and convert model
            if (! $skipModel) {
                if (! $this->installModel($io, $model, $quantization, $force, $tempDir, $keepSource, $pythonPath)) {
                    $hasErrors = true;
                }
            } else {
                $io->note('Skipping model installation (--skip-model).');
            }
        } finally {
            // Always clean up the temp directory
            $this->removeDir($tempDir);
        }

        // Ensure .gitignore exists so installed artifacts are not committed
        $this->ensureGitignore();

        // Summary
        $io->section('Summary');
        if ($hasErrors) {
            $io->warning('Some steps failed. See the messages above for details.');
            $io->text('You may need to complete the installation manually. See the README for instructions.');

            return Command::FAILURE;
        }

        $io->success('Installation complete! Run `neuraphp doctor` to verify your setup.');

        return Command::SUCCESS;
    }

    private function installLibrary(SymfonyStyle $io, bool $force, string $tempDir): bool
    {
        $io->section('Step 1: Installing embedding.cpp library');

        $libDir = $this->projectRoot.'/bin/neuraphp/lib';
        $soPath = $libDir.'/libbert_shared.so';

        // Check if already installed
        if (! $force && file_exists($soPath)) {
            $io->success("Library already installed at: {$soPath}");

            return true;
        }

        // Check prerequisites
        $io->text('Checking prerequisites...');

        $git = $this->findExecutable('git');
        $cmake = $this->findExecutable('cmake');
        $make = $this->findExecutable('make');
        $cargo = $this->findExecutable('cargo');

        if ($git === null) {
            $io->error("'git' is required but not found. Install git and try again.");

            return false;
        }

        if ($cmake === null) {
            $io->error("'cmake' is required but not found. Install cmake and try again.");

            return false;
        }

        if ($make === null) {
            $io->error("'make' is required but not found. Install make (or build-essential) and try again.");

            return false;
        }

        if ($cargo === null) {
            $io->error("'cargo' (Rust toolchain) is required but not found. Install Rust and try again.");
            $io->note('Get Rust: https://www.rust-lang.org/tools/install');
            $io->note('Or run: curl --proto "=https" --tlsv1.2 -sSf https://sh.rustup.rs | sh');

            return false;
        }

        $io->text("  ✓ git: {$git}");
        $io->text("  ✓ cmake: {$cmake}");
        $io->text("  ✓ make: {$make}");
        $io->text("  ✓ cargo: {$cargo}");

        // Clone embedding.cpp into temp directory
        $sourceDir = $tempDir.'/embedding-cpp';

        $io->text('Cloning embedding.cpp repository...');
        $cloneResult = $this->runCommand(
            ['git', 'clone', '--depth', '1', '--recurse-submodules', 'https://github.com/b7s/embedding.cpp', $sourceDir],
            $tempDir,
        );

        if ($cloneResult !== 0) {
            $io->error('Failed to clone embedding.cpp. Check your internet connection and try again.');
            $io->note('Manual alternative: git clone https://github.com/b7s/embedding.cpp');

            return false;
        }

        // Patch Rust source to fix implicit autoref on dereferenced raw pointers
        $this->patchRustSource($sourceDir.'/tokenizers-cpp/rust/src/lib.rs');

        // Patch C++ source to add missing includes
        $this->patchCppSource($sourceDir.'/bert.cpp');

        // Build libbert_shared.so
        $io->text('Compiling libbert_shared.so...');
        $buildPath = $sourceDir.'/build';

        if (! is_dir($buildPath) && ! mkdir($buildPath, 0755, true) && ! is_dir($buildPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $buildPath));
        }

        $cmakeResult = $this->runCommand(
            ['cmake', '..', '-DCMAKE_BUILD_TYPE=Release', '-DBUILD_SHARED_LIBS=OFF', '-DCMAKE_POSITION_INDEPENDENT_CODE=ON'],
            $buildPath,
        );

        if ($cmakeResult !== 0) {
            $io->error('cmake configuration failed. Ensure you have a C++ compiler installed.');
            $io->note('On Ubuntu: sudo apt install build-essential');
            $io->note('On macOS: xcode-select --install');

            return false;
        }

        $makeResult = $this->runCommand(['make', '-j'.(string) $this->getCpuCores(), 'bert_shared'], $buildPath);

        if ($makeResult !== 0) {
            $io->error('Compilation failed. Check the error output above.');

            return false;
        }

        // Find the compiled library
        $compiledLib = $this->findCompiledLibrary($buildPath);

        if ($compiledLib === null) {
            $io->error('Compilation completed but libbert_shared.so was not found in the build directory.');

            return false;
        }

        // Copy to project lib directory
        if (! is_dir($libDir) && ! mkdir($libDir, 0755, true) && ! is_dir($libDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $libDir));
        }

        if (! copy($compiledLib, $soPath)) {
            $io->error("Failed to copy library to {$soPath}.");

            return false;
        }

        chmod($soPath, 0755);
        $io->success("Library installed at: {$soPath}");

        return true;
    }

    private function installModel(SymfonyStyle $io, ModelReference $model, Quantization $quantization, bool $force, string $tempDir, bool $keepSource, string $pythonPath): bool
    {
        $io->section('Step 2: Downloading model');

        $modelDir = $this->projectRoot.'/bin/neuraphp/models/'.$model->directoryName();
        $modelFile = $modelDir.'/'.$model->filename($quantization);

        // Check if already downloaded
        if (! $force && file_exists($modelFile)) {
            $io->success("Model already installed at: {$modelFile}");

            return true;
        }

        // Check prerequisites
        $git = $this->findExecutable('git');
        $gitLfs = $this->findExecutable('git-lfs');

        if ($git === null) {
            $io->error("'git' is required for model download. Install git and try again.");

            return false;
        }

        if ($gitLfs === null) {
            $io->error("'git-lfs' is required for model download. Install git-lfs and try again.");
            $io->note('On Ubuntu/Debian: sudo apt install git-lfs');
            $io->note('On macOS: brew install git-lfs');
            $io->note('Then run: git lfs install');

            return false;
        }

        $io->text(" ✓ git: {$git}");
        $io->text(" ✓ git-lfs: {$gitLfs}");

        if (! $model->isKnown()) {
            $io->note("Custom model: {$model->huggingFaceId()}. Ensure this is a BERT-architecture model compatible with embedding.cpp.");
        }

        // Download model from HuggingFace into temp directory
        $huggingFaceUrl = "https://huggingface.co/{$model->huggingFaceId()}";
        $sourceDir = $tempDir.'/'.$model->directoryName();

        $io->text("Downloading model from HuggingFace: {$model->huggingFaceId()}...");
        $io->note('This may take a while depending on the model size.');

        $cloneResult = $this->runCommand(
            ['git', 'clone', '--depth', '1', $huggingFaceUrl, $sourceDir],
            $tempDir,
        );

        if ($cloneResult !== 0) {
            $io->error("Failed to download model '{$model->huggingFaceId()}' from HuggingFace.");
            $io->note("The model may not exist at: {$huggingFaceUrl}");
            $io->note('Check the model name and try again, or download manually.');

            return false;
        }

        // Try to convert the model
        $io->text('Converting model to GGUF format...');

        $converted = $this->convertModel($io, $sourceDir, $modelDir, $model, $quantization, $tempDir, $pythonPath);

        if (! $converted) {
            $io->warning('Automatic model conversion failed.');
            $io->text('This usually means Python or required packages are not installed.');
            $io->text('');
            $io->text('<comment>Option 1: Use a virtualenv with --python-path</comment>');
            $io->text('  python3 -m venv ~/myenv');
            $io->text('  source ~/myenv/bin/activate');
            $io->text('  pip install torch numpy transformers');
            $io->text('  ./vendor/bin/neuraphp install --python-path=~/myenv/bin/python3');
            $io->text('');
            $io->text('<comment>Option 2: Manual conversion</comment>');
            $io->text('  1. Install Python 3.8+ and pip');
            $io->text('  2. Install requirements: pip install torch numpy transformers');
            $io->text('  3. Convert the model using embedding.cpp/models/convert-to-gguf.py');
            $io->text('');
            $io->text('  Then copy the converted model file to:');
            $io->text("  {$modelFile}");

            return false;
        }

        if (! file_exists($modelFile)) {
            $io->warning("Model conversion completed but expected file not found at: {$modelFile}");
            $io->text('Check the models directory for available files.');

            return false;
        }

        $io->success("Model installed at: {$modelFile}");

        // Clean up intermediate files to free disk space
        if (! $keepSource) {
            $this->removeDir($sourceDir);
            $io->text('Cleaned up model source files to free disk space.');
        }

        $this->removeDir($tempDir.'/embedding-cpp/build');
        $io->text('Cleaned up build artifacts to free disk space.');

        return true;
    }

    private function convertModel(SymfonyStyle $io, string $sourceDir, string $modelDir, ModelReference $model, Quantization $quantization, string $tempDir, string $pythonPath): bool
    {
        // Find Python: explicit path > virtualenv search > system search
        $python = $this->resolvePython($pythonPath);

        if ($python === null) {
            $io->text('Python not found. Cannot convert model automatically.');
            $io->text('');
            $io->text('<comment>Tip:</comment> Install Python in a virtualenv and use --python-path:');
            $io->text('  python3 -m venv ~/myenv');
            $io->text('  source ~/myenv/bin/activate');
            $io->text('  pip install torch numpy transformers');
            $io->text('  ./vendor/bin/neuraphp install --python-path=~/myenv/bin/python3');

            return false;
        }

        $io->text("  ✓ python: {$python}");

        // Find the embedding.cpp source directory (in temp dir from library build)
        $embeddingCppDir = $tempDir.'/embedding-cpp';

        if (! is_dir($embeddingCppDir)) {
            $io->text('embedding.cpp source not found. Cannot convert model.');
            $io->note('Run the library installation step first.');

            return false;
        }

        $convertScript = $embeddingCppDir.'/models/convert-to-gguf.py';

        if (! file_exists($convertScript)) {
            $convertScript = $embeddingCppDir.'/models/convert-to-ggml.py';
        }

        if (! file_exists($convertScript)) {
            $io->text('Conversion script not found. Cannot convert model automatically.');

            return false;
        }

        $isGguf = str_ends_with($convertScript, 'convert-to-gguf.py');

        // Check required Python packages
        $requiredImports = $isGguf
            ? 'import torch, numpy, transformers, gguf, sentencepiece'
            : 'import torch, numpy, transformers';

        $io->text('Checking Python dependencies...');
        $packageCheck = $this->runCommand(
            [$python, '-c', $requiredImports],
            dirname($convertScript),
        );

        if ($packageCheck !== 0) {
            // Try to auto-install dependencies if Python is in a virtualenv
            if ($this->isVirtualenvPython($python)) {
                $io->text('Python dependencies missing. Attempting to install into virtualenv...');
                $installed = $this->installPythonDeps($io, $python, dirname($convertScript), $isGguf);

                if (! $installed) {
                    $io->error('Failed to install Python dependencies automatically.');

                    return false;
                }
            } else {
                $io->error('Required Python packages are missing and Python is not in a virtualenv.');
                $io->note('Create a virtualenv and use --python-path:');
                $io->text('  python3 -m venv ~/myenv');
                $io->text('  source ~/myenv/bin/activate');
                $io->text('  pip install torch numpy transformers');
                $io->text('  ./vendor/bin/neuraphp install --python-path=~/myenv/bin/python3');

                return false;
            }
        }

        // Try to run conversion for f16 first (needed for quantization)
        $io->text('Running model conversion (this requires Python + torch + transformers)...');

        // Convert to f16
        $f16Result = $this->runCommand(
            [$python, $convertScript, $sourceDir.'/', '1'],
            dirname($convertScript),
        );

        if ($f16Result !== 0) {
            $io->text('Python conversion failed. Missing dependencies?');

            return false;
        }

        // Check if quantize binary exists
        $quantizeBin = $embeddingCppDir.'/build/bin/quantize';

        if ($quantization !== Quantization::F16 && $quantization !== Quantization::F32 && file_exists($quantizeBin)) {
            $f16File = null;

            foreach ([
                $sourceDir.'/../ggml-model-f16.gguf',
                $sourceDir.'/../ggml-model-f16.bin',
                $sourceDir.'/ggml-model-f16.gguf',
                $sourceDir.'/ggml-model-f16.bin',
                $modelDir.'/ggml-model-f16.gguf',
                $modelDir.'/ggml-model-f16.bin',
            ] as $candidate) {
                if (file_exists($candidate)) {
                    $f16File = $candidate;
                    break;
                }
            }

            if ($f16File !== null && file_exists($f16File)) {
                $quantizeType = match ($quantization) {
                    Quantization::Q4_0 => '2',
                    Quantization::Q4_1 => '3',
                };

                $outputFile = $modelDir.'/'.$model->filename($quantization);

                $quantizeResult = $this->runCommand(
                    [$quantizeBin, $f16File, $outputFile, $quantizeType],
                    $embeddingCppDir,
                );

                if ($quantizeResult !== 0) {
                    $io->text('Quantization failed. The f16 model may still be usable.');

                    // Try to use f16 as fallback (prefer .gguf, then .bin)
                    $f16Fallback = null;
                    foreach ([$modelDir.'/ggml-model-f16.gguf', $modelDir.'/ggml-model-f16.bin'] as $candidate) {
                        if (file_exists($candidate)) {
                            $f16Fallback = $candidate;
                            break;
                        }
                    }

                    if ($f16Fallback !== null) {
                        $io->text('Falling back to f16 model.');

                        return true;
                    }

                    return false;
                }
            }
        }

        // Copy converted files to model directory
        $this->copyConvertedFiles($sourceDir, $modelDir);

        $expectedFile = $modelDir.'/'.$model->filename($quantization);

        if (file_exists($expectedFile)) {
            return true;
        }

        // Fallback: if quantization was skipped (quantize binary missing),
        // accept the f16 file as a valid fallback
        $f16File = null;

        foreach (['ggml-model-f16.gguf', 'ggml-model-f16.bin'] as $candidate) {
            $path = $modelDir.'/'.$candidate;
            if (file_exists($path)) {
                $f16File = $path;
                break;
            }
        }

        if ($f16File !== null && file_exists($f16File)) {
            $io->text('Quantize binary not available. Using f16 model as fallback.');

            $expectedFile = $modelDir.'/'.$model->filename($quantization);

            if ($expectedFile !== $f16File && ! file_exists($expectedFile)) {
                copy($f16File, $expectedFile);
            }

            return true;
        }

        return false;
    }

    /**
     * Patch the tokenizers-cpp Rust source to fix implicit autoref on dereferenced raw pointers.
     *
     * Rust 2024 edition makes implicit autorefs on dereferenced raw pointers a hard error.
     * This method applies the fix: `(*handle).field.method()` → `(&(*handle).field).method()`
     * and `(*handle).field.as_mut_ptr()` → `(&mut (*handle).field).as_mut_ptr()`.
     */
    private function patchRustSource(string $filePath): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        if (! is_string($content)) {
            return;
        }

        // Fix implicit autoref on .len() calls (shared reference)
        // (*handle).encode_ids.len() → (&(*handle).encode_ids).len()
        // (*handle).decode_str.len() → (& (*handle).decode_str).len()
        $content = preg_replace(
            '/\(\*handle\)\.(\w+)\.len\(\)/',
            '(&(*handle).$1).len()',
            $content,
        ) ?? $content;

        // Fix implicit autoref on .as_mut_ptr() calls (mutable reference)
        // (*handle).encode_ids.as_mut_ptr() → (&mut (*handle).encode_ids).as_mut_ptr()
        // (*handle).decode_str.as_mut_ptr() → (&mut (*handle).decode_str).as_mut_ptr()
        $content = preg_replace(
            '/\(\*handle\)\.(\w+)\.as_mut_ptr\(\)/',
            '(&mut (*handle).$1).as_mut_ptr()',
            $content,
        ) ?? $content;

        file_put_contents($filePath, $content);
    }

    /**
     * Patch bert.cpp to add missing C++ standard library includes.
     *
     * The embedding.cpp repo is missing #include <unordered_map>, <array>, and <mutex>
     * which causes compilation errors with modern GCC.
     */
    private function patchCppSource(string $filePath): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        if (! is_string($content)) {
            return;
        }

        $missingIncludes = [
            '#include <unordered_map>',
            '#include <array>',
            '#include <mutex>',
        ];

        $insertAfter = '#include "tokenizer.h"';
        $patch = '';

        foreach ($missingIncludes as $include) {
            if (! str_contains($content, $include)) {
                $patch .= $include."\n";
            }
        }

        if ($patch !== '') {
            $content = str_replace($insertAfter, $insertAfter."\n".$patch, $content);
        }

        file_put_contents($filePath, $content);
    }

    private function copyConvertedFiles(string $sourceDir, string $modelDir): void
    {
        if (! is_dir($modelDir) && ! mkdir($modelDir, 0755, true) && ! is_dir($modelDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $modelDir));
        }

        $patterns = ['ggml-model-*.gguf', 'ggml-model-*.bin'];

        foreach ($patterns as $pattern) {
            foreach ([$sourceDir.'/../'.$pattern, $sourceDir.'/'.$pattern] as $globPattern) {
                $files = glob($globPattern);
                if ($files === false) {
                    continue;
                }

                foreach ($files as $file) {
                    $filename = basename($file);
                    $dest = $modelDir.'/'.$filename;

                    if (! file_exists($dest)) {
                        copy($file, $dest);
                    }
                }
            }
        }
    }

    private function findCompiledLibrary(string $buildDir): ?string
    {
        // Search common locations for the compiled shared library
        $searchPaths = [
            $buildDir.'/libbert_shared.so',
            $buildDir.'/libbert.so',
            $buildDir.'/bert_shared.so',
        ];

        foreach ($searchPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Search recursively for any .so file
        $soFiles = glob($buildDir.'/**/*.so', GLOB_BRACE);

        if ($soFiles !== false && $soFiles !== []) {
            foreach ($soFiles as $file) {
                if (str_contains($file, 'bert')) {
                    return $file;
                }
            }

            return $soFiles[0];
        }

        return null;
    }

    private function ensureGitignore(): void
    {
        $gitignoreDir = $this->projectRoot.'/bin/neuraphp';

        if (! is_dir($gitignoreDir) && ! mkdir($gitignoreDir, 0755, true) && ! is_dir($gitignoreDir)) {
            return;
        }

        $gitignorePath = $gitignoreDir.'/.gitignore';

        if (file_exists($gitignorePath)) {
            return;
        }

        file_put_contents($gitignorePath, "# Ignore all downloaded/generated files\n*\n!.gitignore\n");
    }

    private function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir().'/neuraphp-build-'.str_replace('.', '_', uniqid('', true));

        if (! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new RuntimeException("Failed to create temp directory: {$tempDir}");
        }

        return $tempDir;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            $path = $file->getPathname();

            if ($file->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    private function findExecutable(string $name): ?string
    {
        // 1. Check common virtualenv locations (project-local and home)
        $homeDir = is_string($_SERVER['HOME'] ?? null) ? $_SERVER['HOME'] : '';
        $projectVenvs = [
            getcwd().'/.venv/bin/'.$name,
            getcwd().'/venv/bin/'.$name,
            getcwd().'/env/bin/'.$name,
        ];
        $homeVenvs = [];

        if ($homeDir !== '') {
            $homeVenvs = [
                $homeDir.'/.venv/bin/'.$name,
                $homeDir.'/venv/bin/'.$name,
                $homeDir.'/env/bin/'.$name,
                $homeDir.'/myenv/bin/'.$name,
            ];
        }

        // Also scan common virtualenv names in home directory
        if ($homeDir !== '' && is_dir($homeDir)) {
            $homeVenvs = array_merge($homeVenvs, $this->findVirtualenvBinaries($homeDir, $name));
        }

        $allPaths = array_merge($projectVenvs, $homeVenvs);

        foreach ($allPaths as $path) {
            $expanded = $this->expandTilde($path);
            if (file_exists($expanded) && is_executable($expanded)) {
                return $expanded;
            }
        }

        // 2. Check standard system paths
        $systemPaths = [
            '/usr/bin/'.$name,
            '/usr/local/bin/'.$name,
            '/opt/homebrew/bin/'.$name,
        ];

        foreach ($systemPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // 3. Try which command
        $result = shell_exec("which {$name} 2>/dev/null");

        if (is_string($result) && trim($result) !== '') {
            return trim($result);
        }

        return null;
    }

    /**
     * Find Python binaries in virtualenv directories under the given base directory.
     *
     * @return list<string>
     */
    private function findVirtualenvBinaries(string $baseDir, string $name): array
    {
        $results = [];
        $candidates = ['myenv', '.venv', 'venv', 'env', '.virtualenvs'];

        foreach ($candidates as $candidate) {
            $binPath = $baseDir.'/'.$candidate.'/bin/'.$name;
            if (file_exists($binPath) && is_executable($binPath)) {
                $results[] = $binPath;
            }
        }

        // Also check ~/.virtualenvs/ directory (common pipenv/pyenv pattern)
        $virtualenvsDir = $baseDir.'/.virtualenvs';
        if (is_dir($virtualenvsDir)) {
            $entries = scandir($virtualenvsDir);
            if ($entries !== false) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $binPath = $virtualenvsDir.'/'.$entry.'/bin/'.$name;
                    if (file_exists($binPath) && is_executable($binPath)) {
                        $results[] = $binPath;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Resolve the Python executable path from explicit option, virtualenv search, or system search.
     */
    private function resolvePython(string $explicitPath): ?string
    {
        // 1. Explicit path provided via --python-path
        if ($explicitPath !== '') {
            $expanded = $this->expandTilde($explicitPath);

            if (file_exists($expanded) && is_executable($expanded)) {
                return $expanded;
            }

            // Not found — report clearly
            return null;
        }

        // 2. Search virtualenvs and system paths
        return $this->findExecutable('python3') ?? $this->findExecutable('python');
    }

    /**
     * Check if a Python executable is inside a virtualenv.
     */
    private function isVirtualenvPython(string $pythonPath): bool
    {
        // Virtualenv Python binaries have a pyvenv.cfg in their parent directory
        $binDir = dirname($pythonPath);
        $venvRoot = dirname($binDir);

        // Check for pyvenv.cfg (standard virtualenv marker)
        if (file_exists($venvRoot.'/pyvenv.cfg')) {
            return true;
        }

        // Check for conda-style environments
        if (file_exists($venvRoot.'/conda-meta')) {
            return true;
        }

        // Check if the Python path contains typical virtualenv directory names
        $normalizedPath = str_replace('\\', '/', $pythonPath);

        return str_contains($normalizedPath, '/.venv/') ||
            str_contains($normalizedPath, '/venv/') ||
            str_contains($normalizedPath, '/env/') ||
            str_contains($normalizedPath, '/myenv/') ||
            str_contains($normalizedPath, '/.virtualenvs/');
    }

    /**
     * Attempt to install Python dependencies into a virtualenv.
     */
    private function installPythonDeps(SymfonyStyle $io, string $pythonPath, string $workingDir, bool $ggufMode = false): bool
    {
        $packages = ['torch', 'numpy', 'transformers'];

        if ($ggufMode) {
            $packages[] = 'gguf';
            $packages[] = 'sentencepiece';
        }

        $io->text('Installing '.implode(', ', $packages).'...');

        // Find pip alongside the Python executable
        $binDir = dirname($pythonPath);
        $pipPath = $binDir.'/pip3';

        if (! file_exists($pipPath) || ! is_executable($pipPath)) {
            $pipPath = $binDir.'/pip';
        }

        if (! file_exists($pipPath) || ! is_executable($pipPath)) {
            // Fall back to using the Python executable with -m pip
            $io->text('pip not found alongside Python, using python -m pip...');

            $result = $this->runCommand(
                array_merge([$pythonPath, '-m', 'pip', 'install'], $packages),
                $workingDir,
            );

            if ($result !== 0) {
                return false;
            }
        } else {
            $result = $this->runCommand(
                array_merge([$pipPath, 'install'], $packages),
                $workingDir,
            );

            if ($result !== 0) {
                return false;
            }
        }

        // Verify installation
        $io->text('Verifying Python dependencies...');
        $verifyImports = $ggufMode
            ? 'import torch, numpy, transformers, gguf, sentencepiece'
            : 'import torch, numpy, transformers';

        $verifyResult = $this->runCommand(
            [$pythonPath, '-c', $verifyImports],
            $workingDir,
        );

        if ($verifyResult !== 0) {
            $io->error('Dependency installation appeared to succeed but verification failed.');

            return false;
        }

        $io->text('  ✓ Python dependencies installed successfully.');

        return true;
    }

    /**
     * Expand ~ to the user's home directory in a file path.
     */
    private function expandTilde(string $path): string
    {
        $homeDir = is_string($_SERVER['HOME'] ?? null) ? $_SERVER['HOME'] : '';

        if ($homeDir !== '' && str_starts_with($path, '~/')) {
            return $homeDir.'/'.substr($path, 2);
        }

        return $path;
    }

    /**
     * @param  list<string>  $command
     */
    private function runCommand(array $command, string $workingDir): int
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($command, $descriptors, $pipes, $workingDir);

        if (! is_resource($process)) {
            return 1;
        }

        fclose($pipes[0]);

        return proc_close($process);
    }

    private function getCpuCores(): int
    {
        $cores = (int) shell_exec('nproc 2>/dev/null') ?: 1;

        return max(1, $cores);
    }
}
