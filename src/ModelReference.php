<?php

declare(strict_types=1);

namespace B7s\Neuraphp;

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;

final readonly class ModelReference
{
    private function __construct(
        private string $huggingFaceId,
        private ?Model $knownModel,
        private ?int $dimensions,
        private ?int $maxTokens,
    ) {}

    /**
     * Create a ModelReference from a known Model enum case.
     */
    public static function fromEnum(Model $model): self
    {
        return new self(
            huggingFaceId: $model->huggingFaceId(),
            knownModel: $model,
            dimensions: $model->dimensions(),
            maxTokens: $model->maxTokens(),
        );
    }

    /**
     * Create a ModelReference from an arbitrary HuggingFace model ID.
     *
     * Use this for models not present in the Model enum.
     * Dimensions and max tokens are unknown — set them via
     * withDimensions() / withMaxTokens() or via config.
     */
    public static function fromId(string $huggingFaceId): self
    {
        $knownModel = Model::tryFromHuggingFaceId($huggingFaceId);

        if ($knownModel !== null) {
            return self::fromEnum($knownModel);
        }

        return new self(
            huggingFaceId: $huggingFaceId,
            knownModel: null,
            dimensions: null,
            maxTokens: null,
        );
    }

    /**
     * Parse a string that may be a known enum value or an arbitrary HuggingFace ID.
     *
     * Examples:
     *   "all-MiniLM-L6-v2"           → known enum case (sentence-transformers/all-MiniLM-L6-v2)
     *   "BAAI/bge-large-en-v1.5"     → known enum case (matched by HF ID)
     *   "custom-org/my-bert-model"   → custom model (no enum match)
     */
    public static function parse(string $value): self
    {
        if (str_contains($value, '/')) {
            return self::fromId($value);
        }

        $knownModel = Model::tryFrom($value);

        if ($knownModel !== null) {
            return self::fromEnum($knownModel);
        }

        return self::fromId($value);
    }

    /**
     * Return a new ModelReference with explicit dimensions.
     */
    public function withDimensions(int $dimensions): self
    {
        return new self(
            huggingFaceId: $this->huggingFaceId,
            knownModel: $this->knownModel,
            dimensions: $dimensions,
            maxTokens: $this->maxTokens,
        );
    }

    /**
     * Return a new ModelReference with explicit max tokens.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        return new self(
            huggingFaceId: $this->huggingFaceId,
            knownModel: $this->knownModel,
            dimensions: $this->dimensions,
            maxTokens: $maxTokens,
        );
    }

    /**
     * The full HuggingFace repository ID, e.g. "BAAI/bge-large-en-v1.5".
     */
    public function huggingFaceId(): string
    {
        return $this->huggingFaceId;
    }

    /**
     * Short directory name derived from the HuggingFace ID (the repo name part).
     *
     * "BAAI/bge-large-en-v1.5" → "bge-large-en-v1.5"
     * "sentence-transformers/all-MiniLM-L6-v2" → "all-MiniLM-L6-v2"
     */
    public function directoryName(): string
    {
        return basename($this->huggingFaceId);
    }

    /**
     * Get the embedding dimensions for this model.
     *
     * Returns null for custom models where dimensions are not configured.
     */
    public function dimensions(): ?int
    {
        return $this->dimensions;
    }

    /**
     * Get the maximum token count for this model.
     *
     * Returns null for custom models where max tokens are not configured.
     */
    public function maxTokens(): ?int
    {
        return $this->maxTokens;
    }

    /**
     * Whether this model is a known Model enum case with full metadata.
     */
    public function isKnown(): bool
    {
        return $this->knownModel !== null;
    }

    /**
     * Get the backing Model enum case, or null for custom models.
     */
    public function toEnum(): ?Model
    {
        return $this->knownModel;
    }

    /**
     * Get the default GGUF model filename for this model with the given quantization.
     */
    public function filename(?Quantization $quantization = null): string
    {
        $quantization ??= Quantization::default();

        return $quantization->filename();
    }

    /**
     * Get the legacy .bin model filename for this model with the given quantization.
     */
    public function legacyFilename(?Quantization $quantization = null): string
    {
        $quantization ??= Quantization::default();

        return $quantization->legacyFilename();
    }

    /**
     * A display-friendly identifier for this model.
     */
    public function displayName(): string
    {
        return $this->huggingFaceId;
    }

    /**
     * Parse a ModelReference from a config array, applying model_dimensions and model_max_tokens if present.
     *
     * @param  array<string, mixed>  $config
     */
    public static function parseFromConfig(array $config): ?self
    {
        if (! isset($config['model']) || ! is_string($config['model'])) {
            return null;
        }

        $ref = self::parse($config['model']);

        if (isset($config['model_dimensions']) && is_int($config['model_dimensions'])) {
            $ref = $ref->withDimensions($config['model_dimensions']);
        }

        if (isset($config['model_max_tokens']) && is_int($config['model_max_tokens'])) {
            $ref = $ref->withMaxTokens($config['model_max_tokens']);
        }

        return $ref;
    }
}
