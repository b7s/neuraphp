<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Support;

use InvalidArgumentException;

final class VectorMath
{
    /**
     * Calculate the cosine similarity between two vectors.
     *
     * Returns a value between -1 and 1, where 1 means identical direction.
     *
     * @param  float[]  $a
     * @param  float[]  $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            throw new InvalidArgumentException('Vectors must not be empty.');
        }

        if (count($a) !== count($b)) {
            throw new InvalidArgumentException(
                sprintf('Vectors must have the same dimension. Got %d and %d.', count($a), count($b))
            );
        }

        $dotProduct = self::dotProduct($a, $b);
        $magnitudeA = self::magnitude($a);
        $magnitudeB = self::magnitude($b);

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Calculate the dot product of two vectors.
     *
     * @param  float[]  $a
     * @param  float[]  $b
     */
    public static function dotProduct(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException(
                sprintf('Vectors must have the same dimension. Got %d and %d.', count($a), count($b))
            );
        }

        $result = 0.0;
        $count = count($a);

        for ($i = 0; $i < $count; $i++) {
            $result += $a[$i] * $b[$i];
        }

        return $result;
    }

    /**
     * Calculate the Euclidean distance between two vectors.
     *
     * @param  float[]  $a
     * @param  float[]  $b
     */
    public static function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException(
                sprintf('Vectors must have the same dimension. Got %d and %d.', count($a), count($b))
            );
        }

        $sum = 0.0;
        $count = count($a);

        for ($i = 0; $i < $count; $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Calculate the magnitude (L2 norm) of a vector.
     *
     * @param  float[]  $v
     */
    public static function magnitude(array $v): float
    {
        if ($v === []) {
            throw new InvalidArgumentException('Vector must not be empty.');
        }

        $sum = 0.0;

        foreach ($v as $component) {
            $sum += $component * $component;
        }

        return sqrt($sum);
    }
}
