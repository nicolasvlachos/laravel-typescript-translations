<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Services;

use Illuminate\Support\Facades\File;

/**
 * Service for handling translation export operations.
 */
class TranslationExportService
{
    public function __construct(
        private readonly NamingService $namingService
    ) {}

    /**
     * Generate locale-mapped content for TypeScript export.
     *
     * @param string $moduleName
     * @param array<string, mixed> $localeData
     * @return string
     */
    public function generateLocaleMappedContent(string $moduleName, array $localeData): string
    {
        $exportName = $this->namingService->getExportName($moduleName);
        
        $content = $this->generateHeader($moduleName);
        
        // Format the JSON with proper indentation
        $jsonContent = json_encode($localeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Export the translations constant
        $content .= "export const {$exportName} = {$jsonContent} as const;\n\n";
        
        // Generate type
        $typeName = $this->namingService->getTypeName($exportName);
        $content .= "export type {$typeName} = typeof {$exportName};\n\n";
        
        // Generate locale keys type
        $locales = array_keys($localeData);
        $localeUnion = implode(' | ', array_map(fn($l) => "'{$l}'", $locales));
        $localesTypeName = $this->namingService->getLocalesTypeName($moduleName);
        $content .= "export type {$localesTypeName} = {$localeUnion};\n\n";
        
        // Generate helper to get translations for a specific locale
        $getterName = $this->namingService->getGetterFunctionName($moduleName);
        $content .= "export function {$getterName}<L extends {$localesTypeName}>(locale: L): {$typeName}[L] {\n";
        $content .= "  return {$exportName}[locale];\n";
        $content .= "}\n";
        
        return $content;
    }

    /**
     * Generate content for a module file with multiple exports.
     *
     * @param string $sourceName
     * @param array<string, array<string, mixed>> $exports
     * @return string
     */
    public function generateModuleFileContent(string $sourceName, array $exports): string
    {
        $content = $this->generateHeader($sourceName);
        
        // Generate each export separately
        foreach ($exports as $exportName => $localeData) {
            $translationsName = $this->namingService->getExportName($exportName);
            
            // Format the JSON with proper indentation
            $jsonContent = json_encode($localeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            // Export the translations constant
            $content .= "export const {$translationsName} = {$jsonContent} as const;\n\n";
            
            // Generate type
            $typeName = $this->namingService->getTypeName($translationsName);
            $content .= "export type {$typeName} = typeof {$translationsName};\n\n";
            
            // Generate locale keys type
            $locales = array_keys($localeData);
            $localeUnion = implode(' | ', array_map(fn($l) => "'{$l}'", $locales));
            $localesTypeName = $this->namingService->getLocalesTypeName($exportName);
            $content .= "export type {$localesTypeName} = {$localeUnion};\n\n";
            
            // Generate helper to get translations for a specific locale
            $getterName = $this->namingService->getGetterFunctionName($exportName);
            $content .= "export function {$getterName}<L extends {$localesTypeName}>(locale: L): {$typeName}[L] {\n";
            $content .= "  return {$translationsName}[locale];\n";
            $content .= "}\n\n";
        }
        
        return $content;
    }

    /**
     * Generate header for a file.
     *
     * @param string $moduleName
     * @return string
     */
    private function generateHeader(string $moduleName): string
    {
        $content = "/**\n";
        $content .= " * Translation module: {$moduleName}\n";
        $content .= " * Generated at: " . now()->toIso8601String() . "\n";
        $content .= " */\n\n";
        
        return $content;
    }

    /**
     * Process source files to build translation object.
     *
     * @param array<string, array<string, mixed>> $files
     * @return array<string, mixed>
     */
    public function processSourceFiles(array $files): array
    {
        $result = [];

        foreach ($files as $file => $translations) {
            if ($file === '_json') {
                // Merge JSON translations at root
                $result = array_merge($result, $translations);
            } else {
                // Build nested structure for PHP files
                $parts = explode('.', $file);
                $current = &$result;
                
                foreach ($parts as $i => $part) {
                    if ($i === count($parts) - 1) {
                        $current[$part] = $translations;
                    } else {
                        if (!isset($current[$part])) {
                            $current[$part] = [];
                        }
                        $current = &$current[$part];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Write file to disk with proper directory creation.
     *
     * @param string $path
     * @param string $content
     * @return void
     */
    public function writeFile(string $path, string $content): void
    {
        $directory = dirname($path);
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, $content);
    }

    /**
     * Generate index file for module exports.
     *
     * @param array<string, array<string, array<string, mixed>>> $modulesBySource
     * @param string $path
     * @param string $format
     * @return void
     */
    public function generateModuleIndex(array $modulesBySource, string $path, string $format): void
    {
        $ext = $this->namingService->getFileExtension($format);
        $indexPath = $path . '/index.' . $ext;
        
        $content = "/**\n";
        $content .= " * Main index for all translation modules\n";
        $content .= " * Generated at: " . now()->toIso8601String() . "\n";
        $content .= " */\n\n";
        
        // Export all from each module file
        foreach ($modulesBySource as $sourceName => $exports) {
            $fileName = $this->namingService->getModuleFileName($sourceName, 'module');
            $content .= "// {$sourceName} translations\n";
            
            // Export all named exports from the module file
            foreach (array_keys($exports) as $exportName) {
                $translationsName = $this->namingService->getExportName($exportName);
                $typeName = $this->namingService->getTypeName($translationsName);
                $localesTypeName = $this->namingService->getLocalesTypeName($exportName);
                
                $content .= "export { {$translationsName} } from './{$fileName}';\n";
                $content .= "export type { {$typeName}, {$localesTypeName} } from './{$fileName}';\n";
            }
            
            $content .= "\n";
        }
        
        $this->writeFile($indexPath, $content);
    }

    /**
     * Generate index file for granular exports.
     *
     * @param array<string, array<string, mixed>> $allModuleData
     * @param string $path
     * @param string $format
     * @return void
     */
    public function generateGranularIndex(array $allModuleData, string $path, string $format): void
    {
        $ext = $this->namingService->getFileExtension($format);
        $indexPath = $path . '/index.' . $ext;
        
        $content = "/**\n";
        $content .= " * Main index for all translation modules\n";
        $content .= " * Generated at: " . now()->toIso8601String() . "\n";
        $content .= " */\n\n";
        
        // Export all modules
        foreach ($allModuleData as $sourceName => $moduleFiles) {
            $moduleDir = $this->namingService->toFilenameSafe($sourceName);
            $content .= "// {$sourceName} translations\n";
            $content .= "export * from './{$moduleDir}';\n\n";
        }
        
        $this->writeFile($indexPath, $content);
    }
}