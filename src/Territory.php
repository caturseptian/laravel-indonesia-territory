<?php

namespace CaturSeptian\IndonesiaTerritory;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use InvalidArgumentException;

/**
 * Helper kode wilayah. Semua method kecuali table() tidak menyentuh database atau config.
 */
final class Territory
{
    /**
     * Nama tabel lengkap dengan prefix dari config, misalnya "territory_provinces".
     */
    public static function table(string $name): string
    {
        return config('territory.prefix', 'territory_').$name;
    }

    public static function level(string $code): ?Level
    {
        $level = Level::cases()[substr_count($code, '.')] ?? null;

        return $level !== null && preg_match($level->pattern(), $code) === 1 ? $level : null;
    }

    public static function isValid(string $code, ?Level $level = null): bool
    {
        $actual = self::level($code);

        return $actual !== null && ($level === null || $actual === $level);
    }

    /**
     * Kode induk per level, mulai dari provinsi. "31.71.01.1001" → province 31, regency 31.71, district 31.71.01.
     *
     * @return array<string, string>
     */
    public static function parents(string $code): array
    {
        $level = self::level($code) ?? throw new InvalidArgumentException("Kode wilayah tidak valid: {$code}.");
        $parts = explode('.', $code);

        $parents = [];
        foreach ($level->withAncestors() as $ancestor) {
            if ($ancestor !== $level) {
                $parents[$ancestor->value] = implode('.', array_slice($parts, 0, $ancestor->depth()));
            }
        }

        return $parents;
    }
}
