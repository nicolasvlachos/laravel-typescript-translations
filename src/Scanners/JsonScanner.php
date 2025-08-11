<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Scanners;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Scanner for JSON translation files.
 */
class JsonScanner implements ScannerInterface
{
    /**
     * Check if this scanner can handle the given file.
     *
     * @param string $filePath
     * @return bool
     */
    public function canHandle(string $filePath): bool
    {
        return Str::endsWith($filePath, '.json');
    }

    /**
     * Scan a JSON file and return its translations.
     *
     * @param string $filePath
     * @return array<string, mixed>
     */
    public function scan(string $filePath): array
    {
        if (!File::exists($filePath)) {
            return [];
        }

        try {
            $content = File::get($filePath);
            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : [];
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
        return 'json';
    }
}