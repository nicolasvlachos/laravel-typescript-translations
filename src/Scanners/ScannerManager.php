<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Scanners;

use Illuminate\Support\Facades\File;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Data\TranslationData;

/**
 * Manages scanning of translation files.
 */
class ScannerManager
{
    /**
     * Available scanners.
     *
     * @var array<ScannerInterface>
     */
    private array $scanners;

    /**
     * Create a new scanner manager instance.
     *
     * @param TranslationConfig $config
     */
    public function __construct(
        private readonly TranslationConfig $config
    ) {
        $this->scanners = [
            new PhpScanner(),
            new JsonScanner(),
        ];
    }

    /**
     * Scan all translation files in the given paths.
     *
     * @param array<string, array<string>> $paths
     * @param array<string> $selectedLocales
     * @return TranslationData
     */
    public function scan(array $paths, array $selectedLocales = []): TranslationData
    {
        $data = new TranslationData();

        foreach ($paths as $source => $sourcePaths) {
            foreach ($sourcePaths as $path) {
                $this->scanPath($path, $source, $selectedLocales, $data);
            }
        }

        return $data;
    }

    /**
     * Scan a single path for translations.
     *
     * @param string $path
     * @param string $source
     * @param array<string> $selectedLocales
     * @param TranslationData $data
     * @return void
     */
    private function scanPath(string $path, string $source, array $selectedLocales, TranslationData $data): void
    {
        // Scan locale directories
        $this->scanLocaleDirectories($path, $source, $selectedLocales, $data);

        // Scan root JSON files (Laravel 9+ style)
        $this->scanRootJsonFiles($path, $source, $selectedLocales, $data);
    }

    /**
     * Scan locale directories.
     *
     * @param string $path
     * @param string $source
     * @param array<string> $selectedLocales
     * @param TranslationData $data
     * @return void
     */
    private function scanLocaleDirectories(string $path, string $source, array $selectedLocales, TranslationData $data): void
    {
        if (!File::exists($path)) {
            return;
        }

        $locales = File::directories($path);

        foreach ($locales as $localePath) {
            $locale = basename($localePath);

            // Skip non-locale directories (common asset directories in Laravel)
            $nonLocaleDirs = ['css', 'js', 'sass', 'scss', 'views', 'components', 'layouts', 'assets', 'images', 'fonts', 'vendor'];
            if (in_array($locale, $nonLocaleDirs)) {
                continue;
            }

            // Only process directories that look like locale codes (e.g., en, en_US, pt_BR)
            // or are at least 2 characters (for custom locale codes)
            if (strlen($locale) < 2) {
                continue;
            }

            // Skip if specific locales are selected and this isn't one
            if (!empty($selectedLocales) && !in_array($locale, $selectedLocales)) {
                continue;
            }

            // Check base language restriction
            $baseLanguage = $this->config->getBaseLanguage();
            if ($baseLanguage && $locale !== $baseLanguage) {
                $data->addLocale($locale);
                continue;
            }

            $data->addLocale($locale);
            $this->scanLocaleDirectory($localePath, $source, '', $data);
        }
    }

    /**
     * Scan a locale directory recursively.
     *
     * @param string $directory
     * @param string $source
     * @param string $subPath
     * @param TranslationData $data
     * @return void
     */
    private function scanLocaleDirectory(string $directory, string $source, string $subPath, TranslationData $data): void
    {
        // Scan files based on configuration
        $scanTypes = $this->config->getScanTypes();

        if ($scanTypes === 'all' || $scanTypes === 'php') {
            $this->scanPhpFiles($directory, $source, $subPath, $data);
        }

        if ($scanTypes === 'all' || $scanTypes === 'json') {
            $this->scanJsonFiles($directory, $source, $subPath, $data);
        }

        // Scan subdirectories
        $subdirectories = File::directories($directory);
        foreach ($subdirectories as $subdirectory) {
            $dirName = basename($subdirectory);
            $newSubPath = $subPath ? $subPath . '.' . $dirName : $dirName;
            $this->scanLocaleDirectory($subdirectory, $source, $newSubPath, $data);
        }
    }

    /**
     * Scan PHP files in a directory.
     *
     * @param string $directory
     * @param string $source
     * @param string $subPath
     * @param TranslationData $data
     * @return void
     */
    private function scanPhpFiles(string $directory, string $source, string $subPath, TranslationData $data): void
    {
        $scanner = $this->getScanner('php');
        $files = File::glob($directory . '/*.php');

        foreach ($files as $file) {
            if ($this->shouldExcludeFile($file)) {
                continue;
            }

            $fileName = basename($file, '.php');
            $fileKey = $subPath ? $subPath . '.' . $fileName : $fileName;

            $content = $scanner->scan($file);
            if (!empty($content)) {
                $data->addTranslations($source, $fileKey, $content);
            }
        }
    }

    /**
     * Scan JSON files in a directory.
     *
     * @param string $directory
     * @param string $source
     * @param string $subPath
     * @param TranslationData $data
     * @return void
     */
    private function scanJsonFiles(string $directory, string $source, string $subPath, TranslationData $data): void
    {
        $scanner = $this->getScanner('json');
        $files = File::glob($directory . '/*.json');

        foreach ($files as $file) {
            if ($this->shouldExcludeFile($file)) {
                continue;
            }

            $content = $scanner->scan($file);
            if (!empty($content)) {
                $jsonKey = '_json' . ($subPath ? '.' . $subPath : '');
                $data->addTranslations($source, $jsonKey, $content);
            }
        }
    }

    /**
     * Scan root JSON files.
     *
     * @param string $path
     * @param string $source
     * @param array<string> $selectedLocales
     * @param TranslationData $data
     * @return void
     */
    private function scanRootJsonFiles(string $path, string $source, array $selectedLocales, TranslationData $data): void
    {
        $scanTypes = $this->config->getScanTypes();
        if ($scanTypes !== 'all' && $scanTypes !== 'json') {
            return;
        }

        $scanner = $this->getScanner('json');
        $files = File::glob($path . '/*.json');

        foreach ($files as $file) {
            $locale = basename($file, '.json');

            // Skip if not a valid locale pattern
            if (!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale)) {
                continue;
            }

            // Skip if specific locales are selected and this isn't one
            if (!empty($selectedLocales) && !in_array($locale, $selectedLocales)) {
                continue;
            }

            // Check base language restriction
            $baseLanguage = $this->config->getBaseLanguage();
            if ($baseLanguage && $locale !== $baseLanguage) {
                $data->addLocale($locale);
                continue;
            }

            $content = $scanner->scan($file);
            if (!empty($content)) {
                $data->addLocale($locale);
                $data->addTranslations($source, '_json', $content);
            }
        }
    }

    /**
     * Check if a file should be excluded.
     *
     * @param string $filePath
     * @return bool
     */
    private function shouldExcludeFile(string $filePath): bool
    {
        $excludedFiles = $this->config->getExcludedFiles();
        $fileName = basename($filePath);
        $fileNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);

        foreach ($excludedFiles as $excluded) {
            $excluded = str_replace('.php', '', $excluded);
            if ($fileNameWithoutExt === $excluded) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get scanner for a given extension.
     *
     * @param string $extension
     * @return ScannerInterface|null
     */
    private function getScanner(string $extension): ?ScannerInterface
    {
        foreach ($this->scanners as $scanner) {
            if ($scanner->getExtension() === $extension) {
                return $scanner;
            }
        }

        return null;
    }
}