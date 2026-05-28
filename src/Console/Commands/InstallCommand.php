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

    private PrerequisiteValidator $validator;

    public function __construct()
    {
        parent::__construct();
        $this->projectRoot = Config::resolveProjectRoot();
        $this->validator = new PrerequisiteValidator;
    }

    protected function configure(): void
    {
        $config = Config::resolve();
        $defaultModel = $config->model()?->huggingFaceId() ?? Model::default()->value;
        $defaultQuantization = $config->quantization()->value;

        $this->addOption('model', null, InputOption::VALUE_OPTIONAL, 'Model to download (enum name or HuggingFace ID like BAAI/bge-large-en-v1.5)', $defaultModel);
        $this->addOption('quantization', null, InputOption::VALUE_OPTIONAL, 'Quantization level', $defaultQuantization);
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

        $tempDir = $this->createTempDir();

        $hasErrors = false;

        try {
            if (! $skipLibrary) {
                if (! $this->installLibrary($io, $force, $tempDir)) {
                    $hasErrors = true;
                    $io->note('Skipping model installation because library installation failed.');
                    $skipModel = true;
                }
            } else {
                $io->note('Skipping library installation (--skip-library).');
            }

            if (! $skipModel) {
                if (! $this->installModel($io, $model, $quantization, $force, $tempDir, $keepSource, $pythonPath)) {
                    $hasErrors = true;
                }
            }
        } finally {
            $this->removeDir($tempDir);
        }

        $this->ensureGitignore();

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

        $libDir = $this->projectRoot.'/bin/neuraphp-data/lib';
        $soPath = $libDir.'/libbert_shared.so';

        if (! $force && file_exists($soPath)) {
            $io->success("Library already installed at: {$soPath}");

            return true;
        }

        if (! $this->validator->validateLibraryPrerequisites($io)) {
            return false;
        }

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

        $this->patchRustSource($sourceDir.'/tokenizers-cpp/rust/src/lib.rs');
        $this->patchCppSource($sourceDir.'/bert.cpp');

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

        $compiledLib = $this->findCompiledLibrary($buildPath);

        if ($compiledLib === null) {
            $io->error('Compilation completed but libbert_shared.so was not found in the build directory.');

            return false;
        }

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

        $modelDir = $this->projectRoot.'/bin/neuraphp-data/models/'.$model->directoryName();
        $modelFile = $modelDir.'/'.$model->filename($quantization);

        if (! $force && file_exists($modelFile)) {
            $io->success("Model already installed at: {$modelFile}");

            return true;
        }

        if (! $this->validator->validateModelPrerequisites($io)) {
            return false;
        }

        if (! $model->isKnown()) {
            $io->note("Custom model: {$model->huggingFaceId()}. Ensure this is a BERT-architecture model compatible with embedding.cpp.");
        }

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

        $io->text('Converting model to GGUF format...');

        $converted = $this->convertModel($io, $sourceDir, $modelDir, $model, $quantization, $tempDir, $pythonPath);

        if (! $converted) {
            $io->warning('Automatic model conversion failed.');
            $io->text('This usually means Python or required packages are not installed.');
            $io->text('');
            $io->text('<comment>Option 1: Use a virtualenv with --python-path</comment>');
            $io->text('  python3 -m venv ~/myenv');
            $io->text('  source ~/myenv/bin/activate');
            $io->text('  pip install torch numpy transformers "gguf>=0.19.0" sentencepiece');
            $io->text('  ./vendor/bin/neuraphp install --python-path=~/myenv/bin/python3');
            $io->text('');
            $io->text('<comment>Option 2: Manual conversion</comment>');
            $io->text('  1. Install Python 3.8+ and pip');
            $io->text('  2. Install requirements: pip install torch numpy transformers "gguf>=0.19.0" sentencepiece');
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
        $python = $this->validator->resolvePython($pythonPath);

        if ($python === null) {
            $io->text('Python not found. Cannot convert model automatically.');
            $io->text('');
            $io->text('<comment>Tip:</comment> Install Python in a virtualenv and use --python-path:');
            $io->text('  python3 -m venv ~/myenv');
            $io->text('  source ~/myenv/bin/activate');
            $io->text('  pip install torch numpy transformers "gguf>=0.19.0" sentencepiece');
            $io->text('  ./vendor/bin/neuraphp install --python-path=~/myenv/bin/python3');

            return false;
        }

        $io->text("  ✓ python: {$python}");

        $embeddingCppDir = $this->ensureEmbeddingCppSource($io, $tempDir);

        if ($embeddingCppDir === null) {
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

        $requiredImports = $isGguf
            ? <<<'PYTHON'
import torch, numpy, transformers, sentencepiece
from packaging.version import Version
import gguf
assert Version(gguf.__version__) >= Version("0.19.0"), f"gguf>=0.19.0 required, got {gguf.__version__}"
assert Version(torch.__version__) >= Version("1.9.0"), f"torch>=1.9.0 required, got {torch.__version__}"
assert Version(numpy.__version__) >= Version("1.20.0"), f"numpy>=1.20.0 required, got {numpy.__version__}"
assert Version(transformers.__version__) >= Version("4.0.0"), f"transformers>=4.0.0 required, got {transformers.__version__}"
PYTHON
            : 'import torch, numpy, transformers';

        $io->text('Checking Python dependencies...');
        $packageCheck = $this->runCommand(
            [$python, '-c', $requiredImports],
            dirname($convertScript),
        );

        if ($packageCheck !== 0) {
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
                $io->text('  pip install torch numpy transformers "gguf>=0.19.0" sentencepiece');
                $io->text('  ./vendor/bin/neuraphp install --python-path=~/myenv/bin/python3');

                return false;
            }
        }

        $io->text('Running model conversion (this requires Python + torch + transformers)...');

        $f16Result = $this->runCommand(
            [$python, $convertScript, $sourceDir.'/', '1'],
            dirname($convertScript),
        );

        if ($f16Result !== 0) {
            $io->text('Python conversion failed. Missing dependencies?');

            return false;
        }

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

        $this->copyConvertedFiles($sourceDir, $modelDir);

        $expectedFile = $modelDir.'/'.$model->filename($quantization);

        if (file_exists($expectedFile)) {
            return true;
        }

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

    private function ensureEmbeddingCppSource(SymfonyStyle $io, string $tempDir): ?string
    {
        $embeddingCppDir = $tempDir.'/embedding-cpp';

        if (is_dir($embeddingCppDir)) {
            return $embeddingCppDir;
        }

        $git = $this->validator->findExecutable('git');

        if ($git === null) {
            $io->error("'git' is required to clone embedding.cpp for model conversion. Install git and try again.");

            return null;
        }

        $io->text('Cloning embedding.cpp repository for model conversion...');

        $cloneResult = $this->runCommand(
            ['git', 'clone', '--depth', '1', '--recurse-submodules', 'https://github.com/b7s/embedding.cpp', $embeddingCppDir],
            $tempDir,
        );

        if ($cloneResult !== 0) {
            $io->error('Failed to clone embedding.cpp for model conversion. Check your internet connection.');

            return null;
        }

        $io->text(' ✓ embedding.cpp cloned for conversion.');

        return $embeddingCppDir;
    }

    private function patchRustSource(string $filePath): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        if (! is_string($content)) {
            return;
        }

        $content = preg_replace(
            '/\(\*handle\)\.(\w+)\.len\(\)/',
            '(&(*handle).$1).len()',
            $content,
        ) ?? $content;

        $content = preg_replace(
            '/\(\*handle\)\.(\w+)\.as_mut_ptr\(\)/',
            '(&mut (*handle).$1).as_mut_ptr()',
            $content,
        ) ?? $content;

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
        $gitignoreDir = $this->projectRoot.'/bin/neuraphp-data';

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

    private function isVirtualenvPython(string $pythonPath): bool
    {
        $binDir = dirname($pythonPath);
        $venvRoot = dirname($binDir);

        if (file_exists($venvRoot.'/pyvenv.cfg')) {
            return true;
        }

        if (file_exists($venvRoot.'/conda-meta')) {
            return true;
        }

        $normalizedPath = str_replace('\\', '/', $pythonPath);

        return str_contains($normalizedPath, '/.venv/') ||
            str_contains($normalizedPath, '/venv/') ||
            str_contains($normalizedPath, '/env/') ||
            str_contains($normalizedPath, '/myenv/') ||
            str_contains($normalizedPath, '/.virtualenvs/');
    }

    private function installPythonDeps(SymfonyStyle $io, string $pythonPath, string $workingDir, bool $ggufMode = false): bool
    {
        $packages = ['torch>=1.9.0', 'numpy>=1.20.0', 'transformers>=4.0.0', 'packaging'];

        if ($ggufMode) {
            $packages[] = 'gguf>=0.19.0';
            $packages[] = 'sentencepiece>=0.1.91';
        }

        $io->text('Installing '.implode(', ', $packages).'...');

        $binDir = dirname($pythonPath);
        $pipPath = $binDir.'/pip3';

        if (! file_exists($pipPath) || ! is_executable($pipPath)) {
            $pipPath = $binDir.'/pip';
        }

        if (! file_exists($pipPath) || ! is_executable($pipPath)) {
            $io->text('pip not found alongside Python, using python -m pip...');

            $result = $this->runCommand(
                array_merge([$pythonPath, '-m', 'pip', 'install', '--upgrade'], $packages),
                $workingDir,
            );

            if ($result !== 0) {
                return false;
            }
        } else {
            $result = $this->runCommand(
                array_merge([$pipPath, 'install', '--upgrade'], $packages),
                $workingDir,
            );

            if ($result !== 0) {
                return false;
            }
        }

        $io->text('Verifying Python dependencies...');
        $verifyImports = $ggufMode
            ? <<<'PYTHON'
import torch, numpy, transformers, sentencepiece
from packaging.version import Version
import gguf
assert Version(gguf.__version__) >= Version("0.19.0"), f"gguf>=0.19.0 required, got {gguf.__version__}"
assert Version(torch.__version__) >= Version("1.9.0"), f"torch>=1.9.0 required, got {torch.__version__}"
assert Version(numpy.__version__) >= Version("1.20.0"), f"numpy>=1.20.0 required, got {numpy.__version__}"
assert Version(transformers.__version__) >= Version("4.0.0"), f"transformers>=4.0.0 required, got {transformers.__version__}"
PYTHON
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
