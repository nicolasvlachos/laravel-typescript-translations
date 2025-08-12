<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Scanners;

use Illuminate\Support\Str;

/**
 * Scanner for PHP translation files.
 */
class PhpScanner implements ScannerInterface
{
    /**
     * Check if this scanner can handle the given file.
     *
     * @param string $filePath
     * @return bool
     */
    public function canHandle(string $filePath): bool
    {
        return Str::endsWith($filePath, '.php');
    }

    /**
     * Scan a PHP file and return its translations.
     *
     * @param string $filePath
     * @return array<string, mixed>
     */
    public function scan(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        try {
            // Clear opcache for this specific file to ensure fresh content
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($filePath, true);
            }
            
            // Clear file stat cache for this file
            clearstatcache(true, $filePath);
            
            $content = require $filePath;
            return is_array($content) ? $content : [];
        } catch (\Exception $e) {
            // Log error or handle it appropriately
            return [];
        }
    }

    /**
     * Get the file extension this scanner handles.
     *
     * @return string
     */
    public function getExtension(): string
    {
        return 'php';
    }
}