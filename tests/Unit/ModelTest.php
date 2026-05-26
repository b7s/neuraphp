<?php

declare(strict_types=1);

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;

describe('Model enum', function () {
    it('has correct values', function () {
        expect(Model::default()->value)->toBe('all-MiniLM-L6-v2')
            ->and(Model::AllMiniLML12V2->value)->toBe('all-MiniLM-L12-v2')
            ->and(Model::ParaphraseMiniLML6V2->value)->toBe('paraphrase-MiniLM-L6-v2')
            ->and(Model::ParaphraseMultilingualMiniLML12V2->value)->toBe('paraphrase-multilingual-MiniLM-L12-v2')
            ->and(Model::BgeSmallENV15->value)->toBe('bge-small-en-v1.5')
            ->and(Model::BgeBaseENV15->value)->toBe('bge-base-en-v1.5');
    });

    it('returns correct dimensions', function () {
        expect(Model::default()->dimensions())->toBe(384)
            ->and(Model::AllMiniLML12V2->dimensions())->toBe(384)
            ->and(Model::BgeBaseENV15->dimensions())->toBe(768);
    });

    it('returns correct max tokens', function () {
        expect(Model::default()->maxTokens())->toBe(512)
            ->and(Model::BgeBaseENV15->maxTokens())->toBe(512);
    });

    it('returns correct filenames for quantization levels', function () {
        expect(Model::default()->filename(Quantization::default()))->toBe('ggml-model-q4_0.bin')
            ->and(Model::default()->filename(Quantization::F32))->toBe('ggml-model-f32.bin')
            ->and(Model::default()->filename(Quantization::F16))->toBe('ggml-model-f16.bin')
            ->and(Model::default()->filename(Quantization::Q4_1))->toBe('ggml-model-q4_1.bin');
    });

    it('returns correct directory names', function () {
        expect(Model::default()->directoryName())->toBe('all-MiniLM-L6-v2')
            ->and(Model::BgeSmallENV15->directoryName())->toBe('bge-small-en-v1.5');
    });

    it('can be created from string value', function () {
        $model = Model::from('all-MiniLM-L6-v2');
        expect($model)->toBe(Model::default());
    });
});
