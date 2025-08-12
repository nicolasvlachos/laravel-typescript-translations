<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Writers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Generators\TypeScriptGenerator;
use NVL\LaravelTypescriptTranslations\Stubs\StubManager;
use NVL\LaravelTypescriptTranslations\Services\NamingService;

/**
 * Base class for TypeScript writers.
 */
abstract class BaseWriter implements WriterInterface
{
    /**
     * Paths that were written.
     *
     * @var array<string>
     */
    protected array $writtenPaths = [];

    /**
     * TypeScript generator instance.
     *
     * @var TypeScriptGenerator
     */
    protected TypeScriptGenerator $generator;

    /**
     * Stub manager instance.
     *
     * @var StubManager
     */
    protected StubManager $stubManager;

    /**
     * Naming service instance.
     *
     * @var NamingService
     */
    protected NamingService $namingService;

    /**
     * Create a new writer instance.
     *
     * @param TranslationConfig $config
     */
    public function __construct(
        protected readonly TranslationConfig $config
    ) {
        $this->generator = new TypeScriptGenerator($config);
        $this->stubManager = new StubManager($config);
        $this->namingService = new NamingService();
    }

    /**
     * Get the output paths that were written.
     *
     * @return array<string>
     */
    public function getWrittenPaths(): array
    {
        return $this->writtenPaths;
    }

    /**
     * Write content to a file.
     *
     * @param string $path
     * @param string $content
     * @return void
     */
    protected function writeFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Clear PHP's file stat cache for this specific file
        clearstatcache(true, $path);
        
        File::put($path, $content);
        $this->writtenPaths[] = $path;
        
        // Touch the file to ensure timestamp is updated
        touch($path);
    }

    /**
     * Get organized output path.
     *
     * @param string $basePath
     * @param string $type
     * @return string
     */
    protected function getOrganizedPath(string $basePath, string $type): string
    {
        $organization = $this->config->getOutputOrganization();
        
        if (!$organization['enabled']) {
            return $basePath;
        }

        return match ($type) {
            'types' => $basePath . '/' . $organization['types_folder'],
            'enums' => $basePath . '/' . $organization['enums_folder'],
            'translations' => $basePath . '/' . $organization['translations_folder'],
            'keys' => $basePath . '/' . $organization['keys_folder'],
            default => $basePath,
        };
    }

    /**
     * Get safe TypeScript key.
     *
     * @param string $key
     * @return string
     */
    protected function getSafeKey(string $key): string
    {
        return $this->generator->getSafeKey($key);
    }

    /**
     * Generate a filename-safe version of a name.
     * Delegates to the naming service for consistency.
     *
     * @param string $name
     * @return string
     */
    protected function toFilenameSafe(string $name): string
    {
        return $this->namingService->toFilenameSafe($name);
    }

    /**
     * Build interface name.
     * Delegates to the naming service for consistency.
     *
     * @param string $sourceName
     * @param string $file
     * @param bool $isJson
     * @return string
     */
    protected function buildInterfaceName(string $sourceName, string $file, bool $isJson = false): string
    {
        $suffix = $this->config->getSuffix();

        if ($isJson) {
            if ($file === '_json') {
                return Str::studly($sourceName) . $suffix;
            }
            // Handle nested JSON files
            return $this->namingService->buildModuleName($sourceName, str_replace('_json.', '', $file)) . $suffix;
        }

        // Use naming service for consistent naming
        return $this->namingService->buildModuleName($sourceName, $file) . $suffix;
    }

    /**
     * Convert a source name to property name format.
     * Delegates to the naming service for consistency.
     *
     * @param string $sourceName
     * @return string
     */
    protected function toPropertyName(string $sourceName): string
    {
        return $this->namingService->toPropertyName($sourceName);
    }
}