<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Console\Commands;

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
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
        $this->projectRoot = dirname(__DIR__, 3);
    }

    protected function configure(): void
    {
        $this->addOption('model', null, InputOption::VALUE_OPTIONAL, 'Model to download', Model::default()->value);
        $this->addOption('quantization', null, InputOption::VALUE_OPTIONAL, 'Quantization level', Quantization::default()->value);
        $this->addOption('skip-library', null, InputOption::VALUE_NONE, 'Skip library compilation');
        $this->addOption('skip-model', null, InputOption::VALUE_NONE, 'Skip model download');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Force re-download/re-compile even if files exist');
        $this->addOption('keep-source', null, InputOption::VALUE_NONE, 'Keep downloaded model source files after conversion');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Neuraphp Installer');

        /** @var string $modelValue */
        $modelValue = $input->getOption('model');
        $model = Model::from($modelValue);

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
                if (! $this->installModel($io, $model, $quantization, $force, $tempDir, $keepSource)) {
                    $hasErrors = true;
                }
            } else {
                $io->note('Skipping model installation (--skip-model).');
            }
        } finally {
            // Always clean up the temp directory
            $this->removeDir($tempDir);
        }

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

        $libDir = $this->projectRoot.'/lib';
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
            ['git', 'clone', '--depth', '1', '--recurse-submodules', 'https://github.com/FFengIll/embedding.cpp', $sourceDir],
            $tempDir,
        );

        if ($cloneResult !== 0) {
            $io->error('Failed to clone embedding.cpp. Check your internet connection and try again.');
            $io->note('Manual alternative: git clone https://github.com/FFengIll/embedding.cpp');

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
            ['cmake', '..', '-DCMAKE_BUILD_TYPE=Release', '-DBUILD_SHARED_LIBS=ON', '-DCMAKE_POSITION_INDEPENDENT_CODE=ON'],
            $buildPath,
        );

        if ($cmakeResult !== 0) {
            $io->error('cmake configuration failed. Ensure you have a C++ compiler installed.');
            $io->note('On Ubuntu: sudo apt install build-essential');
            $io->note('On macOS: xcode-select --install');

            return false;
        }

        $makeResult = $this->runCommand(['make', '-j'.(string) $this->getCpuCores(), 'bert'], $buildPath);

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

    private function installModel(SymfonyStyle $io, Model $model, Quantization $quantization, bool $force, string $tempDir, bool $keepSource): bool
    {
        $io->section('Step 2: Downloading model');

        $modelDir = $this->projectRoot.'/models/'.$model->directoryName();
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

        $io->text("  ✓ git: {$git}");
        $io->text("  ✓ git-lfs: {$gitLfs}");

        // Download model from HuggingFace into temp directory
        $huggingFaceUrl = "https://huggingface.co/sentence-transformers/{$model->directoryName()}";
        $sourceDir = $tempDir.'/'.$model->directoryName();

        $io->text("Downloading model from HuggingFace: {$model->directoryName()}...");
        $io->note('This may take a while depending on the model size.');

        $cloneResult = $this->runCommand(
            ['git', 'clone', '--depth', '1', $huggingFaceUrl, $sourceDir],
            $tempDir,
        );

        if ($cloneResult !== 0) {
            $io->error("Failed to download model '{$model->value}' from HuggingFace.");
            $io->note("The model may not exist at: {$huggingFaceUrl}");
            $io->note('Check the model name and try again, or download manually.');

            return false;
        }

        // Try to convert the model
        $io->text('Converting model to GGUF format...');

        $converted = $this->convertModel($io, $sourceDir, $modelDir, $model, $quantization, $tempDir);

        if (! $converted) {
            $io->warning('Automatic model conversion failed.');
            $io->text('This usually means Python or required packages are not installed.');
            $io->text('');
            $io->text('<comment>Manual conversion steps:</comment>');
            $io->text('');
            $io->text('  1. Install Python 3.8+ and pip');
            $io->text('  2. Install requirements:');
            $io->text('     pip install torch numpy transformers');
            $io->text('  3. Convert the model:');
            $io->text('     cd embedding.cpp/models');
            $io->text('     python3 convert-to-ggml.py <model-dir>/ 0');
            $io->text('     python3 convert-to-ggml.py <model-dir>/ 1');
            $io->text('  4. Quantize (if needed):');
            $io->text('     See embedding.cpp README for quantization commands');
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

    private function convertModel(SymfonyStyle $io, string $sourceDir, string $modelDir, Model $model, Quantization $quantization, string $tempDir): bool
    {
        // Check if Python is available
        $python = $this->findExecutable('python3') ?? $this->findExecutable('python');

        if ($python === null) {
            $io->text('Python not found. Cannot convert model automatically.');

            return false;
        }

        // Find the embedding.cpp source directory (in temp dir from library build)
        $embeddingCppDir = $tempDir.'/embedding-cpp';

        if (! is_dir($embeddingCppDir)) {
            $io->text('embedding.cpp source not found. Cannot convert model.');
            $io->note('Run the library installation step first.');

            return false;
        }

        $convertScript = $embeddingCppDir.'/models/convert-to-ggml.py';

        if (! file_exists($convertScript)) {
            $io->text('Conversion script not found. Cannot convert model automatically.');

            return false;
        }

        // Check required Python packages
        $io->text('Checking Python dependencies (torch, numpy, transformers)...');
        $packageCheck = $this->runCommand(
            [$python, '-c', 'import torch, numpy, transformers'],
            dirname($convertScript),
        );

        if ($packageCheck !== 0) {
            $io->error('Required Python packages are missing.');
            $io->note('Install them with: pip install torch numpy transformers');

            return false;
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
            $f16File = $sourceDir.'/../ggml-model-f16.bin';

            if (! file_exists($f16File)) {
                $f16File = $modelDir.'/ggml-model-f16.bin';
            }

            if (file_exists($f16File)) {
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

                    // Try to use f16 as fallback
                    $f16Output = $modelDir.'/ggml-model-f16.bin';
                    if (file_exists($f16Output)) {
                        $io->text('Falling back to f16 model.');

                        return true;
                    }

                    return false;
                }
            }
        }

        // Copy converted files to model directory
        $this->copyConvertedFiles($sourceDir, $modelDir);

        return file_exists($modelDir.'/'.$model->filename($quantization));
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

        $patterns = ['ggml-model-*.bin'];

        foreach ($patterns as $pattern) {
            $files = glob($sourceDir.'/../'.$pattern);
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

        // Also check source dir itself
        $files = glob($sourceDir.'/ggml-model-*.bin');
        if ($files !== false) {
            foreach ($files as $file) {
                $filename = basename($file);
                $dest = $modelDir.'/'.$filename;

                if (! file_exists($dest)) {
                    copy($file, $dest);
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
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo) {
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
        $paths = [
            '/usr/bin/'.$name,
            '/usr/local/bin/'.$name,
            '/opt/homebrew/bin/'.$name,
        ];

        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try which command
        $result = shell_exec("which {$name} 2>/dev/null");

        if (is_string($result) && trim($result) !== '') {
            return trim($result);
        }

        return null;
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
