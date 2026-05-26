<?php

declare(strict_types=1);

namespace B7s\Neuraphp;

use B7s\Neuraphp\Enums\Quantization;
use JsonException;

final readonly class NeuraphpResult
{
    /**
     * @param  float[]  $vector  The embedding vector
     * @param  int  $dimension  Number of dimensions
     * @param  string  $model  Model name used for embedding
     * @param  Quantization  $quantization  Quantization level used
     * @param  float  $duration  Time taken in seconds
     */
    public function __construct(
        public array $vector,
        public int $dimension,
        public string $model,
        public Quantization $quantization,
        public float $duration,
    ) {}

    /**
     * Create a successful result.
     *
     * @param  float[]  $vector
     */
    public static function make(
        array $vector,
        string $model,
        Quantization $quantization,
        float $duration,
    ): self {
        return new self(
            vector: $vector,
            dimension: count($vector),
            model: $model,
            quantization: $quantization,
            duration: $duration,
        );
    }

    /**
     * Get the embedding vector.
     *
     * @return float[]
     */
    public function vector(): array
    {
        return $this->vector;
    }

    /**
     * Get the number of dimensions.
     */
    public function dimension(): int
    {
        return $this->dimension;
    }

    /**
     * Get the model name.
     */
    public function model(): string
    {
        return $this->model;
    }

    /**
     * Get the quantization level.
     */
    public function quantization(): Quantization
    {
        return $this->quantization;
    }

    /**
     * Get the duration in seconds.
     */
    public function duration(): float
    {
        return $this->duration;
    }

    /**
     * Check if the result is successful (has a non-empty vector).
     */
    public function isSuccess(): bool
    {
        return $this->vector !== [] && $this->dimension > 0;
    }

    /**
     * Convert to array representation.
     *
     * @return array{vector: float[], dimension: int, model: string, quantization: string, duration: float}
     */
    public function toArray(): array
    {
        return [
            'vector' => $this->vector,
            'dimension' => $this->dimension,
            'model' => $this->model,
            'quantization' => $this->quantization->value,
            'duration' => $this->duration,
        ];
    }

    /**
     * Convert to JSON string.
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
