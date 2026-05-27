<?php

declare(strict_types=1);

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\ModelReference;

describe('ModelReference', function () {
    it('creates from a known Model enum case', function () {
        $ref = ModelReference::fromEnum(Model::BgeLargeENV15);

        expect($ref->huggingFaceId())->toBe('BAAI/bge-large-en-v1.5')
            ->and($ref->directoryName())->toBe('bge-large-en-v1.5')
            ->and($ref->dimensions())->toBe(1024)
            ->and($ref->maxTokens())->toBe(512)
            ->and($ref->isKnown())->toBeTrue()
            ->and($ref->toEnum())->toBe(Model::BgeLargeENV15)
            ->and($ref->displayName())->toBe('BAAI/bge-large-en-v1.5');
    });

    it('creates from an arbitrary HuggingFace ID', function () {
        $ref = ModelReference::fromId('custom-org/my-bert-model');

        expect($ref->huggingFaceId())->toBe('custom-org/my-bert-model')
            ->and($ref->directoryName())->toBe('my-bert-model')
            ->and($ref->dimensions())->toBeNull()
            ->and($ref->maxTokens())->toBeNull()
            ->and($ref->isKnown())->toBeFalse()
            ->and($ref->toEnum())->toBeNull()
            ->and($ref->displayName())->toBe('custom-org/my-bert-model');
    });

    it('resolves known models when using fromId with a known HF ID', function () {
        $ref = ModelReference::fromId('BAAI/bge-large-en-v1.5');

        expect($ref->isKnown())->toBeTrue()
            ->and($ref->dimensions())->toBe(1024)
            ->and($ref->toEnum())->toBe(Model::BgeLargeENV15);
    });

    it('parses short model names as known enum cases', function () {
        $ref = ModelReference::parse('all-MiniLM-L6-v2');

        expect($ref->isKnown())->toBeTrue()
            ->and($ref->huggingFaceId())->toBe('sentence-transformers/all-MiniLM-L6-v2')
            ->and($ref->dimensions())->toBe(384);
    });

    it('parses full HuggingFace IDs', function () {
        $ref = ModelReference::parse('intfloat/e5-base-v2');

        expect($ref->isKnown())->toBeTrue()
            ->and($ref->huggingFaceId())->toBe('intfloat/e5-base-v2')
            ->and($ref->dimensions())->toBe(768);
    });

    it('parses unknown HuggingFace IDs as custom models', function () {
        $ref = ModelReference::parse('some-org/unknown-model');

        expect($ref->isKnown())->toBeFalse()
            ->and($ref->huggingFaceId())->toBe('some-org/unknown-model')
            ->and($ref->dimensions())->toBeNull();
    });

    it('parses unknown short names as custom models (treated as HF IDs without org)', function () {
        $ref = ModelReference::parse('my-custom-model');

        expect($ref->isKnown())->toBeFalse()
            ->and($ref->huggingFaceId())->toBe('my-custom-model')
            ->and($ref->directoryName())->toBe('my-custom-model');
    });

    it('sets dimensions on custom models via withDimensions', function () {
        $ref = ModelReference::fromId('custom-org/my-model')
            ->withDimensions(768);

        expect($ref->dimensions())->toBe(768)
            ->and($ref->maxTokens())->toBeNull()
            ->and($ref->isKnown())->toBeFalse();
    });

    it('sets max tokens on custom models via withMaxTokens', function () {
        $ref = ModelReference::fromId('custom-org/my-model')
            ->withDimensions(768)
            ->withMaxTokens(512);

        expect($ref->dimensions())->toBe(768)
            ->and($ref->maxTokens())->toBe(512);
    });

    it('returns correct filenames for quantization levels', function () {
        $ref = ModelReference::fromEnum(Model::default());

        expect($ref->filename(Quantization::default()))->toBe('ggml-model-q4_0.gguf')
            ->and($ref->filename(Quantization::F32))->toBe('ggml-model-f32.gguf')
            ->and($ref->filename(Quantization::F16))->toBe('ggml-model-f16.gguf')
            ->and($ref->filename(Quantization::Q4_1))->toBe('ggml-model-q4_1.gguf');
    });

    it('custom models also return correct filenames', function () {
        $ref = ModelReference::fromId('custom-org/my-model');

        expect($ref->filename(Quantization::Q4_0))->toBe('ggml-model-q4_0.gguf')
            ->and($ref->filename(Quantization::F16))->toBe('ggml-model-f16.gguf');
    });

    it('preserves enum metadata when creating from known model', function () {
        $ref = ModelReference::fromEnum(Model::E5BaseV2);

        expect($ref->huggingFaceId())->toBe('intfloat/e5-base-v2')
            ->and($ref->directoryName())->toBe('e5-base-v2')
            ->and($ref->dimensions())->toBe(768)
            ->and($ref->maxTokens())->toBe(512)
            ->and($ref->isKnown())->toBeTrue()
            ->and($ref->toEnum())->toBe(Model::E5BaseV2);
    });
});
