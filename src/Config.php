<?php

declare(strict_types=1);

namespace B7s\Neuraphp;

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\PoolingMode;
use B7s\Neuraphp\Enums\Quantization;
use InvalidArgumentException;

final class Config
{
    private const int DEFAULT_THREADS = 4;

    private const PoolingMode DEFAULT_POOLING_MODE = PoolingMode::Mean;

    private ?Model $model = null;

    private Quantization $quantization;

    private int $threads;

    private PoolingMode $poolingMode;

    private ?string $modelPath = null;

    private ?string $libraryPath = null;

    private ?string $configPath = null;

    public function __construct()
    {
        $this->quantization = Quantization::default();
        $this->threads = self::DEFAULT_THREADS;
        $this->poolingMode = self::DEFAULT_POOLING_MODE;
    }

    /**
     * Create config from cascading sources: explicit → project → package → defaults.
     *
     * Resolution order:
     * 1. Explicit values set via setter methods
     * 2. Project config file (config/neuraphp.php or .neuraphp.php)
     * 3. Package defaults (stubs/neuraphp-config.php)
     * 4. Hardcoded defaults
     */
    public static function resolve(?string $configPath = null): self
    {
        $config = new self;

        // Layer 3: Package defaults
        $packageDefaults = self::loadConfigFile(dirname(__DIR__).'/stubs/neuraphp-config.php');
        $config->applyArray($packageDefaults);

        // Layer 2: Project config
        $projectConfigPath = $configPath ?? self::findProjectConfig();
        if ($projectConfigPath !== null) {
            $projectConfig = self::loadConfigFile($projectConfigPath);
            $config->applyArray($projectConfig);
        }

        return $config;
    }

    public function model(): ?Model
    {
        return $this->model;
    }

    public function quantization(): Quantization
    {
        return $this->quantization;
    }

    public function threads(): int
    {
        return $this->threads;
    }

    public function poolingMode(): PoolingMode
    {
        return $this->poolingMode;
    }

    public function modelPath(): ?string
    {
        return $this->modelPath;
    }

    public function libraryPath(): ?string
    {
        return $this->libraryPath;
    }

    public function configPath(): ?string
    {
        return $this->configPath;
    }

    public function withModel(Model $model): self
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    public function withQuantization(Quantization $quantization): self
    {
        $clone = clone $this;
        $clone->quantization = $quantization;

        return $clone;
    }

    public function withThreads(int $threads): self
    {
        if ($threads < 1) {
            throw new InvalidArgumentException('Thread count must be at least 1.');
        }

        $clone = clone $this;
        $clone->threads = $threads;

        return $clone;
    }

    public function withPoolingMode(PoolingMode $poolingMode): self
    {
        $clone = clone $this;
        $clone->poolingMode = $poolingMode;

        return $clone;
    }

    public function withModelPath(string $modelPath): self
    {
        $clone = clone $this;
        $clone->modelPath = $modelPath;

        return $clone;
    }

    public function withLibraryPath(string $libraryPath): self
    {
        $clone = clone $this;
        $clone->libraryPath = $libraryPath;

        return $clone;
    }

    public function withConfigPath(string $configPath): self
    {
        $clone = clone $this;
        $clone->configPath = $configPath;

        return $clone;
    }

    /**
     * Resolve the full model file path based on config settings.
     */
    public function resolveModelPath(): string
    {
        if ($this->modelPath !== null) {
            return $this->modelPath;
        }

        $model = $this->model ?? Model::default();

        return dirname(__DIR__).'/models/'.$model->directoryName().'/'.$model->filename($this->quantization);
    }

    /**
     * Resolve the library path by searching common locations.
     *
     * @return string The resolved library path
     *
     * @throws Exceptions\LibraryNotFoundException
     */
    public function resolveLibraryPath(): string
    {
        if ($this->libraryPath !== null) {
            if (! file_exists($this->libraryPath)) {
                throw Exceptions\LibraryNotFoundException::withPath($this->libraryPath);
            }

            return $this->libraryPath;
        }

        $searchPaths = [
            dirname(__DIR__).'/lib/libbert_shared.so',
            '/usr/local/lib/libbert_shared.so',
            '/usr/lib/libbert_shared.so',
        ];

        foreach ($searchPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw Exceptions\LibraryNotFoundException::withSearchPaths($searchPaths);
    }

    /**
     * Apply an array of config values to this config object.
     *
     * @param  array<string, mixed>  $config
     */
    private function applyArray(array $config): void
    {
        if (isset($config['model']) && is_string($config['model'])) {
            $this->model = Model::from($config['model']);
        }

        if (isset($config['quantization']) && is_string($config['quantization'])) {
            $this->quantization = Quantization::from($config['quantization']);
        }

        if (isset($config['threads']) && is_int($config['threads'])) {
            $this->threads = $config['threads'];
        }

        if (isset($config['pooling_mode']) && is_string($config['pooling_mode'])) {
            $this->poolingMode = PoolingMode::from($config['pooling_mode']);
        }

        if (isset($config['model_path']) && is_string($config['model_path'])) {
            $this->modelPath = $config['model_path'];
        }

        if (isset($config['library_path']) && is_string($config['library_path'])) {
            $this->libraryPath = $config['library_path'];
        }
    }

    /**
     * Load a config file and return its array contents.
     *
     * @return array<string, mixed>
     */
    private static function loadConfigFile(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        /** @var array<string, mixed> $config */
        $config = require $path;

        return is_array($config) ? $config : [];
    }

    /**
     * Search for a project-level config file.
     */
    private static function findProjectConfig(): ?string
    {
        $candidates = [
            getcwd().'/config/neuraphp.php',
            getcwd().'/.neuraphp.php',
            getcwd().'/neuraphp.php',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
