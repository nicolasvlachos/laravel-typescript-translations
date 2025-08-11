<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Writers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Generators\TypeScriptGenerator;
use NVL\LaravelTypescriptTranslations\Stubs\StubManager;

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
     * Create a new writer instance.
     *
     * @param TranslationConfig $config
     */
    public function __construct(
        protected readonly TranslationConfig $config
    ) {
        $this->generator = new TypeScriptGenerator($config);
        $this->stubManager = new StubManager($config);
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

        File::put($path, $content);
        $this->writtenPaths[] = $path;
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
     * Build interface name.
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
                return $sourceName . 'Json' . $suffix;
            }
            
            $pathParts = explode('.', $file);
            array_shift($pathParts); // Remove '_json'
            
            $interfaceName = $sourceName;
            foreach ($pathParts as $part) {
                $interfaceName .= Str::studly($part);
            }
            return $interfaceName . 'Json' . $suffix;
        }

        $pathParts = explode('.', $file);
        $interfaceName = $sourceName;
        
        $sourceNameLower = strtolower($sourceName);
        $processedParts = [];
        
        foreach ($pathParts as $part) {
            if (strtolower($part) === $sourceNameLower && empty($processedParts)) {
                continue;
            }
            $processedParts[] = Str::studly($part);
        }
        
        foreach ($processedParts as $part) {
            $interfaceName .= $part;
        }
        
        return $interfaceName . $suffix;
    }
}