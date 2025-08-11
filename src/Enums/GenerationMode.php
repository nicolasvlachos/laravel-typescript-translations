<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Enums;

/**
 * Generation mode enumeration.
 */
enum GenerationMode: string
{
    case SINGLE = 'single';
    case MODULE = 'module';
    case GRANULAR = 'granular';

    /**
     * Check if the mode is single file.
     *
     * @return bool
     */
    public function isSingle(): bool
    {
        return $this === self::SINGLE;
    }

    /**
     * Check if the mode is module.
     *
     * @return bool
     */
    public function isModule(): bool
    {
        return $this === self::MODULE;
    }

    /**
     * Check if the mode is granular.
     *
     * @return bool
     */
    public function isGranular(): bool
    {
        return $this === self::GRANULAR;
    }
}