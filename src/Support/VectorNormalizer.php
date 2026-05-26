<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Support;

use InvalidArgumentException;

final class VectorNormalizer
{
    /**
     * Normalize a vector using L2 normalization.
     *
     * Returns a unit vector (magnitude = 1) in the same direction.
     * If the input vector has zero magnitude, returns the input unchanged.
     *
     * @param  float[]  $v
     * @return float[]
     */
    public static function l2Normalize(array $v): array
    {
        if ($v === []) {
            throw new InvalidArgumentException('Vector must not be empty.');
        }

        $magnitude = VectorMath::magnitude($v);

        if ($magnitude === 0.0) {
            return $v;
        }

        return array_map(static fn (float $component): float => $component / $magnitude, $v);
    }
}
