<?php

declare(strict_types=1);

use B7s\Neuraphp\Enums\PoolingMode;

describe('PoolingMode enum', function () {
    it('has correct values', function () {
        expect(PoolingMode::Mean->value)->toBe('mean');
        expect(PoolingMode::CLS->value)->toBe('cls');
        expect(PoolingMode::Last->value)->toBe('last');
    });

    it('returns correct descriptions', function () {
        expect(PoolingMode::Mean->description())->toBe('Average all token embeddings');
        expect(PoolingMode::CLS->description())->toBe('Use the [CLS] token embedding');
        expect(PoolingMode::Last->description())->toBe('Use the last token embedding');
    });

    it('can be created from string value', function () {
        expect(PoolingMode::from('mean'))->toBe(PoolingMode::Mean);
        expect(PoolingMode::from('cls'))->toBe(PoolingMode::CLS);
    });
});
