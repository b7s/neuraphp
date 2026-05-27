<?php

declare(strict_types=1);

use B7s\Neuraphp\Enums\Quantization;

describe('Quantization enum', function () {
    it('has correct values', function () {
        expect(Quantization::F32->value)->toBe('f32')
            ->and(Quantization::F16->value)->toBe('f16')
            ->and(Quantization::Q4_0->value)->toBe('q4_0')
            ->and(Quantization::Q4_1->value)->toBe('q4_1');
    });

    it('returns correct file suffixes', function () {
        expect(Quantization::F32->fileSuffix())->toBe('f32')
            ->and(Quantization::F16->fileSuffix())->toBe('f16')
            ->and(Quantization::Q4_0->fileSuffix())->toBe('q4_0')
            ->and(Quantization::Q4_1->fileSuffix())->toBe('q4_1');
    });

    it('returns correct GGUF filenames', function () {
        expect(Quantization::F32->filename())->toBe('ggml-model-f32.gguf')
            ->and(Quantization::F16->filename())->toBe('ggml-model-f16.gguf')
            ->and(Quantization::Q4_0->filename())->toBe('ggml-model-q4_0.gguf')
            ->and(Quantization::Q4_1->filename())->toBe('ggml-model-q4_1.gguf');
    });

    it('returns correct legacy filenames', function () {
        expect(Quantization::F32->legacyFilename())->toBe('ggml-model-f32.bin')
            ->and(Quantization::F16->legacyFilename())->toBe('ggml-model-f16.bin')
            ->and(Quantization::Q4_0->legacyFilename())->toBe('ggml-model-q4_0.bin')
            ->and(Quantization::Q4_1->legacyFilename())->toBe('ggml-model-q4_1.bin');
    });

    it('returns human-readable labels', function () {
        expect(Quantization::F32->label())->toBe('Full precision (32-bit float)')
            ->and(Quantization::F16->label())->toBe('Half precision (16-bit float)')
            ->and(Quantization::Q4_0->label())->toBe('4-bit quantization (type 0)')
            ->and(Quantization::Q4_1->label())->toBe('4-bit quantization (type 1)');
    });

    it('can be created from string value', function () {
        expect(Quantization::from('q4_0'))->toBe(Quantization::Q4_0)
            ->and(Quantization::from('f32'))->toBe(Quantization::F32);
    });
});
