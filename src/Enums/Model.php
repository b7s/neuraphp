<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Enums;

enum Model: string
{
    case AllMiniLML6V2 = 'all-MiniLM-L6-v2';
    case AllMiniLML12V2 = 'all-MiniLM-L12-v2';
    case ParaphraseMiniLML6V2 = 'paraphrase-MiniLM-L6-v2';
    case ParaphraseMultilingualMiniLML12V2 = 'paraphrase-multilingual-MiniLM-L12-v2';
    case BgeSmallENV15 = 'bge-small-en-v1.5';
    case BgeBaseENV15 = 'bge-base-en-v1.5';

    /**
     * Get the number of embedding dimensions for this model.
     */
    public function dimensions(): int
    {
        return match ($this) {
            self::AllMiniLML6V2,
            self::AllMiniLML12V2,
            self::ParaphraseMiniLML6V2,
            self::ParaphraseMultilingualMiniLML12V2,
            self::BgeSmallENV15 => 384,
            self::BgeBaseENV15 => 768,
        };
    }

    /**
     * Get the maximum number of tokens this model can process.
     */
    public function maxTokens(): int
    {
        return match ($this) {
            self::AllMiniLML6V2,
            self::BgeBaseENV15,
            self::AllMiniLML12V2,
            self::ParaphraseMiniLML6V2,
            self::ParaphraseMultilingualMiniLML12V2,
            self::BgeSmallENV15 => 512,
        };
    }

    /**
     * Get the default GGUF model filename for this model with the given quantization.
     */
    public function filename(Quantization $quantization = Quantization::Q4_0): string
    {
        return match ($quantization) {
            Quantization::F32 => 'ggml-model-f32.bin',
            Quantization::F16 => 'ggml-model-f16.bin',
            Quantization::Q4_0 => 'ggml-model-q4_0.bin',
            Quantization::Q4_1 => 'ggml-model-q4_1.bin',
        };
    }

    /**
     * Get the default model directory name (matches HuggingFace repo name).
     */
    public function directoryName(): string
    {
        return match ($this) {
            self::AllMiniLML6V2 => 'all-MiniLM-L6-v2',
            self::AllMiniLML12V2 => 'all-MiniLM-L12-v2',
            self::ParaphraseMiniLML6V2 => 'paraphrase-MiniLM-L6-v2',
            self::ParaphraseMultilingualMiniLML12V2 => 'paraphrase-multilingual-MiniLM-L12-v2',
            self::BgeSmallENV15 => 'bge-small-en-v1.5',
            self::BgeBaseENV15 => 'bge-base-en-v1.5',
        };
    }
}
