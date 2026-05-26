<?php

declare(strict_types=1);

use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\NeuraphpResult;

describe('NeuraphpResult', function () {
    it('creates result via constructor', function () {
        $result = new NeuraphpResult(
            vector: [0.1, 0.2, 0.3],
            dimension: 3,
            model: 'all-MiniLM-L6-v2',
            quantization: Quantization::Q4_0,
            duration: 0.012,
        );

        expect($result->vector())->toBe([0.1, 0.2, 0.3]);
        expect($result->dimension())->toBe(3);
        expect($result->model())->toBe('all-MiniLM-L6-v2');
        expect($result->quantization())->toBe(Quantization::Q4_0);
        expect($result->duration())->toBe(0.012);
    });

    it('creates result via make factory', function () {
        $result = NeuraphpResult::make(
            vector: [0.5, 0.6, 0.7],
            model: 'all-MiniLM-L6-v2',
            quantization: Quantization::F16,
            duration: 0.025,
        );

        expect($result->vector())->toBe([0.5, 0.6, 0.7]);
        expect($result->dimension())->toBe(3);
        expect($result->model())->toBe('all-MiniLM-L6-v2');
        expect($result->quantization())->toBe(Quantization::F16);
        expect($result->duration())->toBe(0.025);
    });

    it('reports success for non-empty vector', function () {
        $result = NeuraphpResult::make(
            vector: [0.1, 0.2],
            model: 'test',
            quantization: Quantization::Q4_0,
            duration: 0.01,
        );

        expect($result->isSuccess())->toBeTrue();
    });

    it('reports failure for empty vector', function () {
        $result = new NeuraphpResult(
            vector: [],
            dimension: 0,
            model: 'test',
            quantization: Quantization::Q4_0,
            duration: 0.01,
        );

        expect($result->isSuccess())->toBeFalse();
    });

    it('converts to array', function () {
        $result = NeuraphpResult::make(
            vector: [0.1, 0.2],
            model: 'all-MiniLM-L6-v2',
            quantization: Quantization::Q4_0,
            duration: 0.012,
        );

        $array = $result->toArray();

        expect($array)->toHaveKey('vector');
        expect($array)->toHaveKey('dimension');
        expect($array)->toHaveKey('model');
        expect($array)->toHaveKey('quantization');
        expect($array)->toHaveKey('duration');
        expect($array['dimension'])->toBe(2);
        expect($array['model'])->toBe('all-MiniLM-L6-v2');
        expect($array['quantization'])->toBe('q4_0');
    });

    it('converts to JSON', function () {
        $result = NeuraphpResult::make(
            vector: [0.1, 0.2],
            model: 'all-MiniLM-L6-v2',
            quantization: Quantization::Q4_0,
            duration: 0.012,
        );

        $json = $result->toJson();
        $decoded = json_decode($json, true);

        expect($decoded)->toHaveKey('vector');
        expect($decoded)->toHaveKey('dimension');
        expect($decoded['model'])->toBe('all-MiniLM-L6-v2');
    });

    it('is immutable via readonly properties', function () {
        $result = NeuraphpResult::make(
            vector: [0.1],
            model: 'test',
            quantization: Quantization::Q4_0,
            duration: 0.01,
        );

        expect(fn () => $result->model = 'other')->toThrow(Error::class);
    });
});
