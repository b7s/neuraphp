<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Console\Commands;

use B7s\Neuraphp\Config;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\Neuraphp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InfoCommand extends Command
{
    protected static string $defaultDescription = 'Show model and configuration information';

    protected function configure(): void
    {
        $this->setName('info');
        $this->addOption('model', null, InputOption::VALUE_OPTIONAL, 'Model name', Model::AllMiniLML6V2->value);
        $this->addOption('quantization', null, InputOption::VALUE_OPTIONAL, 'Quantization level', Quantization::Q4_0->value);
        $this->addOption('library-path', null, InputOption::VALUE_OPTIONAL, 'Path to libbert_shared.so');
        $this->addOption('model-path', null, InputOption::VALUE_OPTIONAL, 'Path to model file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $modelValue */
        $modelValue = $input->getOption('model');
        $model = Model::from($modelValue);

        /** @var string $quantizationValue */
        $quantizationValue = $input->getOption('quantization');
        $quantization = Quantization::from($quantizationValue);

        $io->title('Neuraphp Configuration');

        // Model info
        $io->section('Model Information');
        $io->definitionList(
            ['Model' => $model->value],
            ['Dimensions' => (string) $model->dimensions()],
            ['Max Tokens' => (string) $model->maxTokens()],
            ['Quantization' => "{$quantization->value} ({$quantization->label()})"],
            ['Model File' => $model->filename($quantization)],
        );

        // Config resolution
        $io->section('Configuration');
        $config = Config::resolve();
        /** @var string|null $libraryPathOption */
        $libraryPathOption = $input->getOption('library-path');
        if (is_string($libraryPathOption) && $libraryPathOption !== '') {
            $config = $config->withLibraryPath($libraryPathOption);
        }
        /** @var string|null $modelPathOption */
        $modelPathOption = $input->getOption('model-path');
        if (is_string($modelPathOption) && $modelPathOption !== '') {
            $config = $config->withModelPath($modelPathOption);
        }
        $config = $config->withModel($model)->withQuantization($quantization);

        $io->definitionList(
            ['Model Path' => $config->resolveModelPath()],
            ['Library Path' => $config->resolveLibraryPath()],
            ['Threads' => (string) $config->threads()],
            ['Pooling Mode' => $config->poolingMode()->value],
        );

        // Availability
        $io->section('Availability');
        $neuraphp = Neuraphp::make()
            ->model($model)
            ->quantization($quantization);

        if (is_string($libraryPathOption) && $libraryPathOption !== '') {
            $neuraphp = $neuraphp->libraryPath($libraryPathOption);
        }
        if (is_string($modelPathOption) && $modelPathOption !== '') {
            $neuraphp = $neuraphp->modelPath($modelPathOption);
        }

        if ($neuraphp->isAvailable()) {
            $io->success('Neuraphp is available and ready to use.');
        } else {
            $io->warning('Neuraphp is not available. Run "neuraphp doctor" for diagnostics.');
        }

        return Command::SUCCESS;
    }
}
