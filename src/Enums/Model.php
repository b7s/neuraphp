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
    case BgeLargeENV15 = 'bge-large-en-v1.5';
    case BgeSmallZHV15 = 'bge-small-zh-v1.5';
    case BgeBaseZHV15 = 'bge-base-zh-v1.5';
    case BgeLargeZHV15 = 'bge-large-zh-v1.5';
    case E5SmallV2 = 'e5-small-v2';
    case E5BaseV2 = 'e5-base-v2';
    case E5LargeV2 = 'e5-large-v2';
    case MultilingualE5Small = 'multilingual-e5-small';
    case MultilingualE5Base = 'multilingual-e5-base';

    public static function default(): self
    {
        return self::AllMiniLML6V2;
    }

    /**
     * Get the full HuggingFace repository ID for this model.
     *
     * This returns the complete path including the organization prefix,
     * e.g. "sentence-transformers/all-MiniLM-L6-v2" or "BAAI/bge-large-en-v1.5".
     */
    public function huggingFaceId(): string
    {
        return match ($this) {
            self::AllMiniLML6V2,
            self::AllMiniLML12V2,
            self::ParaphraseMiniLML6V2,
            self::ParaphraseMultilingualMiniLML12V2 => 'sentence-transformers/'.$this->value,
            self::BgeSmallENV15,
            self::BgeBaseENV15,
            self::BgeLargeENV15,
            self::BgeSmallZHV15,
            self::BgeBaseZHV15,
            self::BgeLargeZHV15 => 'BAAI/'.$this->value,
            self::E5SmallV2,
            self::E5BaseV2,
            self::E5LargeV2,
            self::MultilingualE5Small,
            self::MultilingualE5Base => 'intfloat/'.$this->value,
        };
    }

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
            self::BgeSmallENV15,
            self::E5SmallV2,
            self::MultilingualE5Small => 384,
            self::BgeSmallZHV15 => 512,
            self::BgeBaseENV15,
            self::BgeBaseZHV15,
            self::E5BaseV2,
            self::MultilingualE5Base => 768,
            self::BgeLargeENV15,
            self::BgeLargeZHV15,
            self::E5LargeV2 => 1024,
        };
    }

    /**
     * Get the maximum number of tokens this model can process.
     */
    public function maxTokens(): int
    {
        return 512;
    }

    /**
     * Get the default GGUF model filename for this model with the given quantization.
     */
    public function filename(Quantization $quantization = Quantization::Q4_0): string
    {
        return $quantization->filename();
    }

    /**
     * Get the default model directory name (matches HuggingFace repo name).
     */
    public function directoryName(): string
    {
        return $this->value;
    }

    /**
     * Try to find a known Model enum case matching the given HuggingFace ID.
     *
     * Accepts either a short model name (e.g. "all-MiniLM-L6-v2") or a
     * full HuggingFace ID (e.g. "BAAI/bge-large-en-v1.5").
     */
    public static function tryFromHuggingFaceId(string $id): ?self
    {
        if (str_contains($id, '/')) {
            $shortName = basename($id);
            $candidate = self::tryFrom($shortName);
            if ($candidate !== null && $candidate->huggingFaceId() === $id) {
                return $candidate;
            }

            return null;
        }

        return self::tryFrom($id);
    }
}
