<?php

declare(strict_types=1);

use B7s\Neuraphp\Support\VectorMath;

describe('VectorMath', function () {
    describe('cosineSimilarity', function () {
        it('returns 1 for identical vectors', function () {
            $v = [1.0, 0.0, 0.0];
            $similarity = VectorMath::cosineSimilarity($v, $v);
            expect($similarity)->toBe(1.0);
        });

        it('returns -1 for opposite vectors', function () {
            $a = [1.0, 0.0, 0.0];
            $b = [-1.0, 0.0, 0.0];
            $similarity = VectorMath::cosineSimilarity($a, $b);
            expect($similarity)->toBe(-1.0);
        });

        it('returns 0 for orthogonal vectors', function () {
            $a = [1.0, 0.0];
            $b = [0.0, 1.0];
            $similarity = VectorMath::cosineSimilarity($a, $b);
            expect($similarity)->toBe(0.0);
        });

        it('computes similarity for arbitrary vectors', function () {
            $a = [1.0, 2.0, 3.0];
            $b = [4.0, 5.0, 6.0];
            // dot = 4+10+18 = 32, |a| = sqrt(14), |b| = sqrt(77)
            // cos = 32 / (sqrt(14) * sqrt(77)) ≈ 0.9746
            $similarity = VectorMath::cosineSimilarity($a, $b);
            expect($similarity)->toBeGreaterThan(0.97);
            expect($similarity)->toBeLessThan(0.98);
        });

        it('throws on empty vectors', function () {
            VectorMath::cosineSimilarity([], [1.0]);
        })->throws(InvalidArgumentException::class);

        it('throws on dimension mismatch', function () {
            VectorMath::cosineSimilarity([1.0, 2.0], [1.0]);
        })->throws(InvalidArgumentException::class);

        it('returns 0 for zero vectors', function () {
            $similarity = VectorMath::cosineSimilarity([0.0, 0.0], [1.0, 0.0]);
            expect($similarity)->toBe(0.0);
        });
    });

    describe('dotProduct', function () {
        it('computes dot product correctly', function () {
            $a = [1.0, 2.0, 3.0];
            $b = [4.0, 5.0, 6.0];
            expect(VectorMath::dotProduct($a, $b))->toBe(32.0);
        });

        it('returns 0 for orthogonal vectors', function () {
            $a = [1.0, 0.0];
            $b = [0.0, 1.0];
            expect(VectorMath::dotProduct($a, $b))->toBe(0.0);
        });

        it('throws on dimension mismatch', function () {
            VectorMath::dotProduct([1.0], [1.0, 2.0]);
        })->throws(InvalidArgumentException::class);
    });

    describe('euclideanDistance', function () {
        it('returns 0 for identical vectors', function () {
            $v = [1.0, 2.0, 3.0];
            expect(VectorMath::euclideanDistance($v, $v))->toBe(0.0);
        });

        it('computes distance correctly', function () {
            $a = [0.0, 0.0];
            $b = [3.0, 4.0];
            expect(VectorMath::euclideanDistance($a, $b))->toBe(5.0);
        });

        it('throws on dimension mismatch', function () {
            VectorMath::euclideanDistance([1.0], [1.0, 2.0]);
        })->throws(InvalidArgumentException::class);
    });

    describe('magnitude', function () {
        it('computes magnitude of unit vector', function () {
            expect(VectorMath::magnitude([1.0, 0.0, 0.0]))->toBe(1.0);
        });

        it('computes magnitude of 3-4-5 triangle', function () {
            expect(VectorMath::magnitude([3.0, 4.0]))->toBe(5.0);
        });

        it('throws on empty vector', function () {
            VectorMath::magnitude([]);
        })->throws(InvalidArgumentException::class);
    });
});
