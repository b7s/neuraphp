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
            ->and(Model::BgeBaseENV15->value)->toBe('bge-base-en-v1.5')
            ->and(Model::BgeLargeENV15->value)->toBe('bge-large-en-v1.5')
            ->and(Model::BgeSmallZHV15->value)->toBe('bge-small-zh-v1.5')
            ->and(Model::BgeBaseZHV15->value)->toBe('bge-base-zh-v1.5')
            ->and(Model::BgeLargeZHV15->value)->toBe('bge-large-zh-v1.5')
            ->and(Model::E5SmallV2->value)->toBe('e5-small-v2')
            ->and(Model::E5BaseV2->value)->toBe('e5-base-v2')
            ->and(Model::E5LargeV2->value)->toBe('e5-large-v2')
            ->and(Model::MultilingualE5Small->value)->toBe('multilingual-e5-small')
            ->and(Model::MultilingualE5Base->value)->toBe('multilingual-e5-base');
    });

    it('returns correct dimensions', function () {
        expect(Model::default()->dimensions())->toBe(384)
            ->and(Model::AllMiniLML12V2->dimensions())->toBe(384)
            ->and(Model::BgeBaseENV15->dimensions())->toBe(768)
            ->and(Model::BgeSmallZHV15->dimensions())->toBe(512)
            ->and(Model::BgeLargeENV15->dimensions())->toBe(1024)
            ->and(Model::E5BaseV2->dimensions())->toBe(768)
            ->and(Model::E5LargeV2->dimensions())->toBe(1024)
            ->and(Model::MultilingualE5Base->dimensions())->toBe(768);
    });

    it('returns correct max tokens', function () {
        expect(Model::default()->maxTokens())->toBe(512)
            ->and(Model::BgeBaseENV15->maxTokens())->toBe(512)
            ->and(Model::BgeLargeENV15->maxTokens())->toBe(512)
            ->and(Model::E5BaseV2->maxTokens())->toBe(512);
    });

    it('returns correct filenames for quantization levels', function () {
        expect(Model::default()->filename(Quantization::default()))->toBe('ggml-model-q4_0.gguf')
            ->and(Model::default()->filename(Quantization::F32))->toBe('ggml-model-f32.gguf')
            ->and(Model::default()->filename(Quantization::F16))->toBe('ggml-model-f16.gguf')
            ->and(Model::default()->filename(Quantization::Q4_1))->toBe('ggml-model-q4_1.gguf');
    });

    it('returns correct directory names', function () {
        expect(Model::default()->directoryName())->toBe('all-MiniLM-L6-v2')
            ->and(Model::BgeSmallENV15->directoryName())->toBe('bge-small-en-v1.5')
            ->and(Model::BgeLargeENV15->directoryName())->toBe('bge-large-en-v1.5')
            ->and(Model::E5BaseV2->directoryName())->toBe('e5-base-v2');
    });

    it('returns correct HuggingFace IDs', function () {
        expect(Model::default()->huggingFaceId())->toBe('sentence-transformers/all-MiniLM-L6-v2')
            ->and(Model::BgeLargeENV15->huggingFaceId())->toBe('BAAI/bge-large-en-v1.5')
            ->and(Model::E5BaseV2->huggingFaceId())->toBe('intfloat/e5-base-v2')
            ->and(Model::BgeBaseZHV15->huggingFaceId())->toBe('BAAI/bge-base-zh-v1.5');
    });

    it('can be created from string value', function () {
        $model = Model::from('all-MiniLM-L6-v2');
        expect($model)->toBe(Model::default());
    });

    it('resolves known models from HuggingFace IDs', function () {
        expect(Model::tryFromHuggingFaceId('BAAI/bge-large-en-v1.5'))->toBe(Model::BgeLargeENV15)
            ->and(Model::tryFromHuggingFaceId('intfloat/e5-base-v2'))->toBe(Model::E5BaseV2)
            ->and(Model::tryFromHuggingFaceId('sentence-transformers/all-MiniLM-L6-v2'))->toBe(Model::AllMiniLML6V2)
            ->and(Model::tryFromHuggingFaceId('unknown-org/some-model'))->toBeNull()
            ->and(Model::tryFromHuggingFaceId('all-MiniLM-L6-v2'))->toBe(Model::AllMiniLML6V2);
    });
});
