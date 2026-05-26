<?php

declare(strict_types=1);

use B7s\Neuraphp\Support\VectorNormalizer;

describe('VectorNormalizer', function () {
    describe('l2Normalize', function () {
        it('normalizes a vector to unit length', function () {
            $v = [3.0, 4.0];
            $normalized = VectorNormalizer::l2Normalize($v);

            $magnitude = sqrt($normalized[0] ** 2 + $normalized[1] ** 2);
            expect($magnitude)->toBe(1.0);
        });

        it('preserves direction of the vector', function () {
            $v = [3.0, 4.0];
            $normalized = VectorNormalizer::l2Normalize($v);

            // Direction should be preserved: ratio of components stays the same
            $ratio = $normalized[0] / $normalized[1];
            $expected = $v[0] / $v[1];
            expect(abs($ratio - $expected))->toBeLessThan(0.0001);
        });

        it('returns unit vector unchanged', function () {
            $v = [1.0, 0.0, 0.0];
            $normalized = VectorNormalizer::l2Normalize($v);

            expect($normalized[0])->toBe(1.0);
            expect($normalized[1])->toBe(0.0);
            expect($normalized[2])->toBe(0.0);
        });

        it('handles zero vector by returning unchanged', function () {
            $v = [0.0, 0.0];
            $normalized = VectorNormalizer::l2Normalize($v);

            expect($normalized)->toBe([0.0, 0.0]);
        });

        it('throws on empty vector', function () {
            VectorNormalizer::l2Normalize([]);
        })->throws(InvalidArgumentException::class);

        it('normalizes a larger vector correctly', function () {
            $v = [1.0, 2.0, 3.0, 4.0, 5.0];
            $normalized = VectorNormalizer::l2Normalize($v);

            $magnitude = sqrt(array_sum(array_map(fn (float $x): float => $x * $x, $normalized)));
            expect($magnitude)->toBe(1.0);
        });
    });
});
