<?php

declare(strict_types=1);

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\ModelReference;
use B7s\Neuraphp\Neuraphp;

describe('Neuraphp builder', function () {
    it('creates instance via make factory', function () {
        $builder = Neuraphp::make();
        expect($builder)->toBeInstanceOf(Neuraphp::class);
    });

    it('supports fluent chaining with Model enum', function () {
        $builder = Neuraphp::make()
            ->model(Model::default())
            ->quantization(Quantization::default())
            ->threads(4);

        expect($builder)->toBeInstanceOf(Neuraphp::class);
    });

    it('supports fluent chaining with ModelReference', function () {
        $builder = Neuraphp::make()
            ->model(ModelReference::fromEnum(Model::default()))
            ->quantization(Quantization::default())
            ->threads(4);

        expect($builder)->toBeInstanceOf(Neuraphp::class);
    });

    it('returns correct default dimension for AllMiniLML6V2', function () {
        $dimension = Neuraphp::make()->dimension();
        expect($dimension)->toBe(384);
    });

    it('returns correct dimension for BgeBaseENV15 via Model enum', function () {
        $dimension = Neuraphp::make()
            ->model(Model::BgeBaseENV15)
            ->dimension();
        expect($dimension)->toBe(768);
    });

    it('returns correct dimension for BgeBaseENV15 via ModelReference', function () {
        $dimension = Neuraphp::make()
            ->model(ModelReference::fromEnum(Model::BgeBaseENV15))
            ->dimension();
        expect($dimension)->toBe(768);
    });

    it('returns correct dimension for BgeLargeENV15', function () {
        $dimension = Neuraphp::make()
            ->model(ModelReference::fromEnum(Model::BgeLargeENV15))
            ->dimension();
        expect($dimension)->toBe(1024);
    });

    it('returns null dimension for custom model without configured dimensions', function () {
        $dimension = Neuraphp::make()
            ->model(ModelReference::fromId('custom-org/my-model'))
            ->dimension();
        expect($dimension)->toBeNull();
    });

    it('returns configured dimension for custom model with dimensions', function () {
        $dimension = Neuraphp::make()
            ->model(ModelReference::fromId('custom-org/my-model')->withDimensions(768))
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
        $dimension = Neuraphp::make()->dimension();
        expect($dimension)->toBe(Model::default()->dimensions());
    });
});
