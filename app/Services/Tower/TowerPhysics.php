<?php

namespace App\Services\Tower;

/**
 * The whole physics of Chat Tower, in block-width units (a block is 4 wide).
 *
 * A block lands with an `offset` relative to the block below. Unaimed
 * (`!stack`) it lands anywhere up to a quarter block off centre either side.
 * Aimed (`!stack left` / `!stack right`) it lands on that side, and further
 * out than an unaimed block ever goes - that is how chat fights a lean. It
 * is placement, never a shove: nobody can push the tower, only choose where
 * their own block goes.
 *
 * The lean is the top block's `x`, the running sum of offsets. The tower
 * also sways, and the sway grows with height, so the safe zone shrinks the
 * taller it gets. It topples when lean plus sway crosses the fall line. That
 * is deterministic from what the overlay draws (both fall lines are on
 * screen), so chat can see it coming; the only randomness is where an
 * individual block lands.
 *
 * Pure functions so the rule is unit-testable and lives in exactly one place.
 * Retuning is a constant here, not a rebuild.
 */
final class TowerPhysics
{
    public const float BLOCK_WIDTH = 4.0;

    /** Units from the base centre at which the tower falls. */
    public const float FALL_LINE = 4.0;

    /** Sway amplitude per block of height. */
    public const float SWAY_PER_BLOCK = 0.055;

    /** An unaimed block lands within this many units of the block below, either side. */
    public const float UNAIMED_MAX = 1.0;

    /** An aimed block lands at least this far, and at most AIMED_MAX, to the aimed side. */
    public const float AIMED_MIN = 0.45;

    public const float AIMED_MAX = 1.6;

    /**
     * The aim word a chatter typed after `!stack`, reduced to left / right /
     * null. Anything unrecognised is an unaimed stack, never an error - the
     * bot must not lecture chat about spelling.
     */
    public static function normalizeAim(?string $raw): ?string
    {
        $word = strtolower(trim((string) $raw));

        return match ($word) {
            'left', 'l' => 'left',
            'right', 'r' => 'right',
            default => null,
        };
    }

    /**
     * Where a new block lands relative to the block below. `$u` is a uniform
     * draw in [0, 1); pass one to make the result deterministic, otherwise
     * one is drawn.
     */
    public static function offsetFor(?string $aim, ?float $u = null): float
    {
        $u ??= mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();
        $u = max(0.0, min($u, 0.999999));

        $offset = match ($aim) {
            'left' => -(self::AIMED_MIN + $u * (self::AIMED_MAX - self::AIMED_MIN)),
            'right' => self::AIMED_MIN + $u * (self::AIMED_MAX - self::AIMED_MIN),
            default => -self::UNAIMED_MAX + $u * (2 * self::UNAIMED_MAX),
        };

        return round($offset, 3);
    }

    public static function swayAmplitude(int $height): float
    {
        return round(self::SWAY_PER_BLOCK * max(0, $height), 3);
    }

    /**
     * How far the top of the sway is from the nearer fall line. Negative
     * means the tower has fallen.
     */
    public static function room(float $xTop, int $height): float
    {
        return round(self::FALL_LINE - (abs($xTop) + self::swayAmplitude($height)), 3);
    }

    public static function topples(float $xTop, int $height): bool
    {
        return self::room($xTop, $height) < 0;
    }

    /** 'left', 'right' or 'straight' - the word the bot uses for the lean. */
    public static function leanSide(float $xTop): string
    {
        if ($xTop > 0.3) {
            return 'right';
        }

        if ($xTop < -0.3) {
            return 'left';
        }

        return 'straight';
    }
}
