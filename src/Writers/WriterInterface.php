<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Writers;

use NVL\LaravelTypescriptTranslations\Data\TranslationData;

/**
 * Interface for TypeScript writers.
 */
interface WriterInterface
{
    /**
     * Write TypeScript definitions for the given translation data.
     *
     * @param TranslationData $data
     * @return void
     */
    public function write(TranslationData $data): void;

    /**
     * Get the output paths that were written.
     *
     * @return array<string>
     */
    public function getWrittenPaths(): array;
}