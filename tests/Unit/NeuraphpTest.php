<?php

declare(strict_types=1);

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\Neuraphp;

describe('Neuraphp builder', function () {
    it('creates instance via make factory', function () {
        $builder = Neuraphp::make();
        expect($builder)->toBeInstanceOf(Neuraphp::class);
    });

    it('supports fluent chaining', function () {
        $builder = Neuraphp::make()
            ->model(Model::AllMiniLML6V2)
            ->quantization(Quantization::Q4_0)
            ->threads(4);

        expect($builder)->toBeInstanceOf(Neuraphp::class);
    });

    it('returns correct default dimension for AllMiniLML6V2', function () {
        $dimension = Neuraphp::make()->dimension();
        expect($dimension)->toBe(384);
    });

    it('returns correct dimension for BgeBaseENV15', function () {
        $dimension = Neuraphp::make()
            ->model(Model::BgeBaseENV15)
            ->dimension();
        expect($dimension)->toBe(768);
    });

    it('throws on empty text for embed', function () {
        Neuraphp::make()->embed('');
    })->throws(InvalidArgumentException::class, 'Text to embed must not be empty.');

    it('throws on empty array for embedBatch', function () {
        Neuraphp::make()->embedBatch([]);
    })->throws(InvalidArgumentException::class, 'Texts array must not be empty.');

    it('throws on empty text for cosineSimilarity first arg', function () {
        Neuraphp::make()->cosineSimilarity('', 'test');
    })->throws(InvalidArgumentException::class, 'First text must not be empty.');

    it('throws on empty text for cosineSimilarity second arg', function () {
        Neuraphp::make()->cosineSimilarity('test', '');
    })->throws(InvalidArgumentException::class, 'Second text must not be empty.');

    it('throws on invalid thread count', function () {
        Neuraphp::make()->threads(0);
    })->throws(InvalidArgumentException::class, 'Thread count must be at least 1.');

    it('defaults to AllMiniLML6V2 when model not specified', function () {
        // dimension() uses the default model
        $dimension = Neuraphp::make()->dimension();
        expect($dimension)->toBe(Model::AllMiniLML6V2->dimensions());
    });
});
