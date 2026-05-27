<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Console\Commands;

use B7s\Neuraphp\Config;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\Exceptions\FFIException;
use B7s\Neuraphp\Exceptions\LibraryNotFoundException;
use B7s\Neuraphp\Exceptions\ModelNotFoundException;
use B7s\Neuraphp\ModelReference;
use B7s\Neuraphp\Neuraphp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'doctor',
    description: 'Check if Neuraphp is properly configured and ready to use',
)]
final class DoctorCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('library-path', null, InputOption::VALUE_OPTIONAL, 'Path to libbert_shared.so');
        $this->addOption('model-path', null, InputOption::VALUE_OPTIONAL, 'Path to model file');
        $this->addOption('model', null, InputOption::VALUE_OPTIONAL, 'Model name (enum name or HuggingFace ID)', Model::default()->value);
        $this->addOption('quantization', null, InputOption::VALUE_OPTIONAL, 'Quantization level', Quantization::default()->value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Neuraphp Doctor');

        $allPassed = true;

        // Check 1: FFI Extension
        $io->section('FFI Extension');
        if (extension_loaded('ffi')) {
            $io->success('FFI extension is loaded.');
        } else {
            $io->error('FFI extension is NOT loaded. Install and enable the PHP FFI extension.');
            $allPassed = false;
        }

        // Check 2: Library
        $io->section('Shared Library');
        $config = Config::resolve();
        /** @var string|null $libraryPathOption */
        $libraryPathOption = $input->getOption('library-path');
        if (is_string($libraryPathOption) && $libraryPathOption !== '') {
            $config = $config->withLibraryPath($libraryPathOption);
        }

        try {
            $libraryPath = $config->resolveLibraryPath();
            $io->success("Library found at: {$libraryPath}");
        } catch (LibraryNotFoundException $e) {
            $io->error($e->getMessage());
            $io->note('You need to compile embedding.cpp to get libbert_shared.so. See README for instructions.');
            $allPassed = false;
        }

        // Check 3: Model
        $io->section('Model File');
        /** @var string|null $modelPathOption */
        $modelPathOption = $input->getOption('model-path');
        if (is_string($modelPathOption) && $modelPathOption !== '') {
            $config = $config->withModelPath($modelPathOption);
        }
        /** @var string|null $modelOption */
        $modelOption = $input->getOption('model');
        if (is_string($modelOption) && $modelOption !== '') {
            $config = $config->withModel(ModelReference::parse($modelOption));
        }
        /** @var string|null $quantizationOption */
        $quantizationOption = $input->getOption('quantization');
        if (is_string($quantizationOption) && $quantizationOption !== '') {
            $config = $config->withQuantization(Quantization::from($quantizationOption));
        }

        try {
            $modelPath = $config->resolveModelPath();
            if (file_exists($modelPath)) {
                $io->success("Model found at: {$modelPath}");
            } else {
                $io->error("Model file not found at: {$modelPath}");
                $io->note('Download and convert a model. See README for instructions.');
                $allPassed = false;
            }
        } catch (ModelNotFoundException $e) {
            $io->error($e->getMessage());
            $allPassed = false;
        }

        // Check 4: Test encoding
        if ($allPassed) {
            $io->section('Test Encoding');
            try {
                $neuraphp = Neuraphp::make();

                if (is_string($libraryPathOption) && $libraryPathOption !== '') {
                    $neuraphp = $neuraphp->libraryPath($libraryPathOption);
                }
                if (is_string($modelPathOption) && $modelPathOption !== '') {
                    $neuraphp = $neuraphp->modelPath($modelPathOption);
                }
                if (is_string($modelOption) && $modelOption !== '') {
                    $neuraphp = $neuraphp->model(ModelReference::parse($modelOption));
                }
                if (is_string($quantizationOption) && $quantizationOption !== '') {
                    $neuraphp = $neuraphp->quantization(Quantization::from($quantizationOption));
                }

                $result = $neuraphp->embed('Hello world');

                if ($result->isSuccess() && $result->dimension() > 0) {
                    $io->success("Encoding works! Dimensions: {$result->dimension()}, Duration: ".round($result->duration(), 4).'s');
                } else {
                    $io->error('Encoding returned empty result.');
                    $allPassed = false;
                }
            } catch (FFIException $e) {
                $io->error("FFI error: {$e->getMessage()}");
                $allPassed = false;
            } catch (\Throwable $e) {
                $io->error("Unexpected error: {$e->getMessage()}");
                $allPassed = false;
            }
        }

        $io->section('Summary');
        if ($allPassed) {
            $io->success('All checks passed! Neuraphp is ready to use.');

            return Command::SUCCESS;
        }

        $io->error('Some checks failed. Please fix the issues above.');

        return Command::FAILURE;
    }
}
