<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Enums;

enum Quantization: string
{
    case F32 = 'f32';
    case F16 = 'f16';
    case Q4_0 = 'q4_0';
    case Q4_1 = 'q4_1';

    public static function default(): self
    {
        return self::Q4_0;
    }

    /**
     * Get the GGUF file suffix for this quantization level.
     */
    public function fileSuffix(): string
    {
        return match ($this) {
            self::F32 => 'f32',
            self::F16 => 'f16',
            self::Q4_0 => 'q4_0',
            self::Q4_1 => 'q4_1',
        };
    }

    /**
     * Get the GGUF model filename prefix.
     */
    public function filename(): string
    {
        return match ($this) {
            self::F32 => 'ggml-model-f32.gguf',
            self::F16 => 'ggml-model-f16.gguf',
            self::Q4_0 => 'ggml-model-q4_0.gguf',
            self::Q4_1 => 'ggml-model-q4_1.gguf',
        };
    }

    public function legacyFilename(): string
    {
        return match ($this) {
            self::F32 => 'ggml-model-f32.bin',
            self::F16 => 'ggml-model-f16.bin',
            self::Q4_0 => 'ggml-model-q4_0.bin',
            self::Q4_1 => 'ggml-model-q4_1.bin',
        };
    }

    /**
     * Get a human-readable label for this quantization level.
     */
    public function label(): string
    {
        return match ($this) {
            self::F32 => 'Full precision (32-bit float)',
            self::F16 => 'Half precision (16-bit float)',
            self::Q4_0 => '4-bit quantization (type 0)',
            self::Q4_1 => '4-bit quantization (type 1)',
        };
    }
}
