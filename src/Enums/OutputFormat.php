<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Enums;

/**
 * Output format enumeration.
 */
enum OutputFormat: string
{
    case NESTED = 'nested';
    case FLAT = 'flat';

    /**
     * Check if the format is nested.
     *
     * @return bool
     */
    public function isNested(): bool
    {
        return $this === self::NESTED;
    }

    /**
     * Check if the format is flat.
     *
     * @return bool
     */
    public function isFlat(): bool
    {
        return $this === self::FLAT;
    }
}