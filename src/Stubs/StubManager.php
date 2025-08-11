<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Stubs;

use Illuminate\Support\Facades\File;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;

/**
 * Manages stub templates for TypeScript generation.
 */
class StubManager
{
    /**
     * Default stubs path.
     *
     * @var string
     */
    private string $defaultStubsPath;

    /**
     * Create a new stub manager instance.
     *
     * @param TranslationConfig $config
     */
    public function __construct(
        private readonly TranslationConfig $config
    ) {
        $this->defaultStubsPath = __DIR__ . '/../../stubs';
    }

    /**
     * Get stub content.
     *
     * @param string $stubName
     * @param array<string, string> $replacements
     * @return string
     */
    public function get(string $stubName, array $replacements = []): string
    {
        $stubPath = $this->getStubPath($stubName);
        
        if (!File::exists($stubPath)) {
            throw new \RuntimeException("Stub file not found: {$stubName}");
        }
        
        $content = File::get($stubPath);
        
        foreach ($replacements as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
        }
        
        return $content;
    }

    /**
     * Get the path to a stub file.
     *
     * @param string $stubName
     * @return string
     */
    private function getStubPath(string $stubName): string
    {
        $customPath = $this->config->getStubsPath();
        
        if ($customPath) {
            $customStubPath = base_path($customPath . '/' . $stubName);
            if (File::exists($customStubPath)) {
                return $customStubPath;
            }
        }
        
        return $this->defaultStubsPath . '/' . $stubName;
    }

    /**
     * Check if a stub exists.
     *
     * @param string $stubName
     * @return bool
     */
    public function exists(string $stubName): bool
    {
        return File::exists($this->getStubPath($stubName));
    }
}