<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Scanners;

/**
 * Interface for translation file scanners.
 */
interface ScannerInterface
{
    /**
     * Check if this scanner can handle the given file.
     *
     * @param string $filePath
     * @return bool
     */
    public function canHandle(string $filePath): bool;

    /**
     * Scan a file and return its translations.
     *
     * @param string $filePath
     * @return array<string, mixed>
     */
    public function scan(string $filePath): array;

    /**
     * Get the file extension this scanner handles.
     *
     * @return string
     */
    public function getExtension(): string;
}