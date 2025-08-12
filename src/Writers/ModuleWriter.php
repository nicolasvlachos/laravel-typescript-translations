<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Writers;

use Illuminate\Support\Str;
use NVL\LaravelTypescriptTranslations\Data\TranslationData;

/**
 * Writes translations as separate module files.
 */
class ModuleWriter extends BaseWriter
{
    /**
     * Write TypeScript definitions for the given translation data.
     *
     * @param TranslationData $data
     * @return void
     */
    public function write(TranslationData $data): void
    {
        if ($data->isEmpty()) {
            return;
        }

        $outputConfig = $this->config->getOutput();
        $basePath = base_path($outputConfig->getPath());
        $translationsDir = $basePath . '/translations';

        $sources = $data->getSources();
        $locales = $data->getLocales();

        // Generate module files
        foreach ($sources as $sourceName => $structure) {
            if (empty($structure)) {
                continue;
            }

            $fileName = $this->toFilenameSafe($sourceName) . '.translations.d.ts';
            $filePath = $translationsDir . '/' . $fileName;
            
            $content = $this->generateModuleContent($sourceName, $structure, $locales);
            $this->writeFile($filePath, $content);
        }

        // Generate shared types
        $sharedContent = $this->generateSharedTypes($locales);
        $this->writeFile($translationsDir . '/shared.types.d.ts', $sharedContent);

        // Generate index file
        $indexContent = $this->generateIndexContent($sources, $locales);
        $this->writeFile($basePath . '/translations.d.ts', $indexContent);
    }

    /**
     * Generate module content.
     *
     * @param string $sourceName
     * @param array<string, array<string, mixed>> $structure
     * @param array<string> $locales
     * @return string
     */
    private function generateModuleContent(string $sourceName, array $structure, array $locales): string
    {
        $suffix = $this->config->getSuffix();
        $ts = $this->generator->generateHeader("Module: {$sourceName}");
        
        $ts .= "import type { Locale } from './shared.types';\n\n";

        // Generate granular interfaces for each file
        $fileInterfaces = [];
        
        foreach ($structure as $file => $translations) {
            $isJson = Str::startsWith($file, '_json');
            $interfaceName = $this->buildInterfaceName($sourceName, $file, $isJson);
            $fileInterfaces[$file] = $interfaceName;

            if ($isJson) {
                $comment = "// JSON translations\n";
            } else {
                $displayPath = str_replace('.', '/', $file) . '.php';
                $comment = "// Translations from {$displayPath}\n";
            }

            $ts .= $comment;
            $ts .= "export interface {$interfaceName} {\n";
            $ts .= $this->generator->generateStructure($translations, 2);
            $ts .= "}\n\n";

            // Generate keys type if enabled
            if ($this->config->shouldExportKeys()) {
                $keyTypeName = str_replace($suffix, 'Keys', $interfaceName);
                $keys = $this->generator->generateKeys($translations);
                $ts .= $this->generator->generateKeysType($keys, $keyTypeName);
                $ts .= "\n";
            }
        }

        // Generate combined module interface
        $mainInterfaceName = $sourceName . $suffix;
        $ts .= "// Combined interface for {$sourceName} module\n";
        $ts .= "export interface {$mainInterfaceName} {\n";

        foreach ($structure as $file => $translations) {
            if ($file === '_json') {
                // Inline JSON at root
                foreach ($translations as $key => $value) {
                    $safeKey = $this->getSafeKey($key);
                    if (is_array($value)) {
                        $ts .= "  {$safeKey}: {\n";
                        $ts .= $this->generator->generateStructure($value, 4);
                        $ts .= "  };\n";
                    } else {
                        $ts .= "  {$safeKey}: string;\n";
                    }
                }
            } else {
                $interfaceName = $fileInterfaces[$file];
                $propertyName = preg_replace('/[^a-zA-Z0-9_]/', '_', $file);
                $ts .= "  {$propertyName}: {$interfaceName};\n";
            }
        }

        $ts .= "}\n\n";

        // Generate localized type
        $ts .= "// Localized translations for {$sourceName}\n";
        $ts .= "export type {$sourceName}Localized{$suffix} = {\n";
        $ts .= "  [key in Locale]: {$mainInterfaceName};\n";
        $ts .= "};\n";

        return $ts;
    }

    /**
     * Generate shared types content.
     *
     * @param array<string> $locales
     * @return string
     */
    private function generateSharedTypes(array $locales): string
    {
        $localeUnion = !empty($locales) 
            ? implode(' | ', array_map(fn($l) => "'{$l}'", $locales))
            : 'string';

        return $this->stubManager->get('shared-types.stub', [
            'localeType' => "export type Locale = {$localeUnion};"
        ]);
    }

    /**
     * Generate index content.
     *
     * @param array<string, array<string, array<string, mixed>>> $sources
     * @param array<string> $locales
     * @return string
     */
    private function generateIndexContent(array $sources, array $locales): string
    {
        $suffix = $this->config->getSuffix();
        $ts = $this->generator->generateHeader("Main index");

        // Export all modules
        foreach ($sources as $sourceName => $structure) {
            if (empty($structure)) {
                continue;
            }
            $moduleFile = './translations/' . $this->toFilenameSafe($sourceName) . '.translations';
            $ts .= "export * from '{$moduleFile}';\n";
        }

        $ts .= "export * from './translations/shared.types';\n\n";

        // Generate combined interface
        $ts .= "// Combined translations interface\n";
        $ts .= "export interface {$suffix} {\n";
        
        foreach ($sources as $sourceName => $structure) {
            if (empty($structure)) {
                continue;
            }
            $propertyName = Str::camel($sourceName);
            $interfaceName = $sourceName . $suffix;
            $ts .= "  {$propertyName}: {$interfaceName};\n";
        }
        
        $ts .= "}\n\n";

        // Generate localized type
        $ts .= "// Localized translations\n";
        $ts .= "export type Localized{$suffix} = {\n";
        $ts .= "  [key in Locale]: {$suffix};\n";
        $ts .= "};\n";

        return $ts;
    }
}