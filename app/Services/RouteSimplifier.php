<?php

namespace App\Services;

class RouteSimplifier
{
    /**
     * Simplify a polyline using the Ramer-Douglas-Peucker algorithm.
     *
     * @param  array<int, array{0: float, 1: float}>  $points  [[lng, lat], ...]
     * @param  float  $epsilon  Tolerance in degrees (~0.00005 = ~5 meters)
     * @return array<int, array{0: float, 1: float}>
     */
    public static function simplify(array $points, float $epsilon = 0.00005): array
    {
        if (count($points) <= 2) {
            return $points;
        }

        $maxDistance = 0;
        $maxIndex = 0;

        $start = $points[0];
        $end = $points[count($points) - 1];

        for ($i = 1; $i < count($points) - 1; $i++) {
            $distance = self::perpendicularDistance($points[$i], $start, $end);
            if ($distance > $maxDistance) {
                $maxDistance = $distance;
                $maxIndex = $i;
            }
        }

        if ($maxDistance > $epsilon) {
            $left = self::simplify(array_slice($points, 0, $maxIndex + 1), $epsilon);
            $right = self::simplify(array_slice($points, $maxIndex), $epsilon);

            // Remove duplicate point at the junction
            array_pop($left);

            return array_merge($left, $right);
        }

        return [$start, $end];
    }

    /**
     * Perpendicular distance from a point to a line segment.
     *
     * @param  array{0: float, 1: float}  $point
     * @param  array{0: float, 1: float}  $lineStart
     * @param  array{0: float, 1: float}  $lineEnd
     */
    private static function perpendicularDistance(array $point, array $lineStart, array $lineEnd): float
    {
        $dx = $lineEnd[0] - $lineStart[0];
        $dy = $lineEnd[1] - $lineStart[1];

        if ($dx === 0.0 && $dy === 0.0) {
            return sqrt(
                ($point[0] - $lineStart[0]) ** 2 + ($point[1] - $lineStart[1]) ** 2
            );
        }

        $t = (($point[0] - $lineStart[0]) * $dx + ($point[1] - $lineStart[1]) * $dy)
            / ($dx * $dx + $dy * $dy);

        $t = max(0, min(1, $t));

        $closestX = $lineStart[0] + $t * $dx;
        $closestY = $lineStart[1] + $t * $dy;

        return sqrt(($point[0] - $closestX) ** 2 + ($point[1] - $closestY) ** 2);
    }
}
