<?php

declare(strict_types=1);

namespace B7s\Neuraphp;

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\PoolingMode;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\Support\VectorMath;
use InvalidArgumentException;
use Throwable;

final class Neuraphp
{
    private ?Model $model = null;

    private ?Quantization $quantization = null;

    private ?int $threads = null;

    private ?string $modelPath = null;

    private ?string $libraryPath = null;

    private ?string $configPath = null;

    private ?PoolingMode $poolingMode = null;

    private ?NeuraphpService $service = null;

    private function __construct()
    {
        // Fluent builder — use Neuraphp::make() to create instances
    }

    /**
     * Create a new fluent builder instance.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Set the model to use for embedding.
     * If not called, defaults to Model::AllMiniLML6V2.
     */
    public function model(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the quantization level.
     */
    public function quantization(Quantization $quantization): self
    {
        $this->quantization = $quantization;

        return $this;
    }

    /**
     * Set the number of threads for encoding.
     */
    public function threads(int $threads): self
    {
        if ($threads < 1) {
            throw new InvalidArgumentException('Thread count must be at least 1.');
        }

        $this->threads = $threads;

        return $this;
    }

    /**
     * Set the pooling mode.
     */
    public function poolingMode(PoolingMode $poolingMode): self
    {
        $this->poolingMode = $poolingMode;

        return $this;
    }

    /**
     * Override the model file path.
     */
    public function modelPath(string $modelPath): self
    {
        $this->modelPath = $modelPath;

        return $this;
    }

    /**
     * Override the library file path.
     */
    public function libraryPath(string $libraryPath): self
    {
        $this->libraryPath = $libraryPath;

        return $this;
    }

    /**
     * Override the config file path.
     */
    public function configPath(string $configPath): self
    {
        $this->configPath = $configPath;

        return $this;
    }

    /**
     * Embed a single text string and return the result.
     *
     * This is a terminal operation — it executes the embedding.
     */
    public function embed(string $text): NeuraphpResult
    {
        if ($text === '') {
            throw new InvalidArgumentException('Text to embed must not be empty.');
        }

        $service = $this->resolveService();
        $resolvedModel = $this->model ?? Model::AllMiniLML6V2;

        $start = hrtime(true);
        $vector = $service->encode($text);
        $duration = (hrtime(true) - $start) / 1_000_000_000;

        return NeuraphpResult::make(
            vector: $vector,
            model: $resolvedModel->value,
            quantization: $this->quantization ?? Quantization::Q4_0,
            duration: $duration,
        );
    }

    /**
     * Embed multiple text strings and return an array of results.
     *
     * This is a terminal operation — it executes the embedding.
     *
     * @param  string[]  $texts
     * @return NeuraphpResult[]
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            throw new InvalidArgumentException('Texts array must not be empty.');
        }

        $service = $this->resolveService();
        $resolvedModel = $this->model ?? Model::AllMiniLML6V2;
        $resolvedQuantization = $this->quantization ?? Quantization::Q4_0;

        $start = hrtime(true);
        $vectors = $service->encodeBatch($texts);
        $duration = (hrtime(true) - $start) / 1_000_000_000;

        $durationPerText = $duration / count($texts);

        return array_map(
            static fn (array $vector, int $i): NeuraphpResult => NeuraphpResult::make(
                vector: $vector,
                model: $resolvedModel->value,
                quantization: $resolvedQuantization,
                duration: $durationPerText,
            ),
            $vectors,
            array_keys($texts),
        );
    }

    /**
     * Compute the cosine similarity between two text strings.
     *
     * This is a terminal operation — it embeds both texts and computes similarity.
     */
    public function cosineSimilarity(string $textA, string $textB): float
    {
        if ($textA === '') {
            throw new InvalidArgumentException('First text must not be empty.');
        }

        if ($textB === '') {
            throw new InvalidArgumentException('Second text must not be empty.');
        }

        $results = $this->embedBatch([$textA, $textB]);

        return VectorMath::cosineSimilarity(
            $results[0]->vector(),
            $results[1]->vector(),
        );
    }

    /**
     * Get the number of dimensions for the configured model.
     */
    public function dimension(): int
    {
        return ($this->model ?? Model::AllMiniLML6V2)->dimensions();
    }

    /**
     * Check if FFI, the library, and model are available.
     */
    public function isAvailable(): bool
    {
        try {
            return $this->resolveService()->isAvailable();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Resolve the NeuraphpService from current configuration.
     */
    private function resolveService(): NeuraphpService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $config = Config::resolve($this->configPath);

        // Apply explicit overrides from the builder
        if ($this->model !== null) {
            $config = $config->withModel($this->model);
        }

        if ($this->quantization !== null) {
            $config = $config->withQuantization($this->quantization);
        }

        if ($this->threads !== null) {
            $config = $config->withThreads($this->threads);
        }

        if ($this->poolingMode !== null) {
            $config = $config->withPoolingMode($this->poolingMode);
        }

        if ($this->modelPath !== null) {
            $config = $config->withModelPath($this->modelPath);
        }

        if ($this->libraryPath !== null) {
            $config = $config->withLibraryPath($this->libraryPath);
        }

        $this->service = NeuraphpService::fromConfig($config);

        return $this->service;
    }
}
