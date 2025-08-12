<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Writers;

use Illuminate\Support\Str;
use NVL\LaravelTypescriptTranslations\Data\TranslationData;

/**
 * Writes translations as granular files for each translation file.
 */
class GranularWriter extends BaseWriter
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

        $allInterfaces = [];

        // Generate granular files for each module
        foreach ($sources as $sourceName => $structure) {
            if (empty($structure)) {
                continue;
            }

            $moduleDir = $translationsDir . '/' . $this->toFilenameSafe($sourceName);
            $moduleInterfaces = [];

            foreach ($structure as $file => $translations) {
                $isJson = Str::startsWith($file, '_json');
                $interfaceName = $this->buildInterfaceName($sourceName, $file, $isJson);

                if ($isJson) {
                    $fileName = 'json.translations.d.ts';
                    $property = 'json';
                } else {
                    $fileName = $this->toFilenameSafe($file) . '.translations.d.ts';
                    $property = preg_replace('/[^a-zA-Z0-9_]/', '_', $file);
                }

                $filePath = $moduleDir . '/' . $fileName;
                $content = $this->generateGranularContent($interfaceName, $translations);
                $this->writeFile($filePath, $content);

                $moduleInterfaces[] = [
                    'name' => $interfaceName,
                    'file' => $fileName,
                    'property' => $property
                ];
            }

            // Generate module index
            $moduleIndexContent = $this->generateModuleIndex($sourceName, $moduleInterfaces);
            $this->writeFile($moduleDir . '/index.d.ts', $moduleIndexContent);

            $allInterfaces[$sourceName] = $moduleInterfaces;
        }

        // Generate main index
        $mainIndexContent = $this->generateMainIndex($allInterfaces, $locales);
        $this->writeFile($translationsDir . '/index.d.ts', $mainIndexContent);

        // Generate shared types
        $sharedContent = $this->generateSharedTypes($locales);
        $this->writeFile($translationsDir . '/shared.types.d.ts', $sharedContent);
    }

    /**
     * Generate granular file content.
     *
     * @param string $interfaceName
     * @param array<string, mixed> $translations
     * @return string
     */
    private function generateGranularContent(string $interfaceName, array $translations): string
    {
        $ts = $this->generator->generateHeader($interfaceName);

        $ts .= "export interface {$interfaceName} {\n";
        $ts .= $this->generator->generateStructure($translations, 2);
        $ts .= "}\n\n";

        // Generate keys type if enabled
        if ($this->config->shouldExportKeys()) {
            $suffix = $this->config->getSuffix();
            $keyTypeName = str_replace($suffix, 'Keys', $interfaceName);
            $keys = $this->generator->generateKeys($translations);
            $ts .= $this->generator->generateKeysType($keys, $keyTypeName);
        }

        return $ts;
    }

    /**
     * Generate module index file.
     *
     * @param string $sourceName
     * @param array<array{name: string, file: string, property: string}> $interfaces
     * @return string
     */
    private function generateModuleIndex(string $sourceName, array $interfaces): string
    {
        $suffix = $this->config->getSuffix();
        $ts = "// Module index for {$sourceName} translations\n\n";

        // Import all interfaces
        foreach ($interfaces as $interface) {
            $fileName = str_replace('.d.ts', '', $interface['file']);
            $ts .= "import type { {$interface['name']} } from './{$fileName}';\n";
        }

        $ts .= "\n// Combined module interface\n";
        $ts .= "export interface {$sourceName}{$suffix} {\n";

        foreach ($interfaces as $interface) {
            $ts .= "  {$interface['property']}: {$interface['name']};\n";
        }

        $ts .= "}\n\n";

        // Export all interfaces
        foreach ($interfaces as $interface) {
            $fileName = str_replace('.d.ts', '', $interface['file']);
            $ts .= "export * from './{$fileName}';\n";
        }

        return $ts;
    }

    /**
     * Generate main index file.
     *
     * @param array<string, array<array{name: string, file: string, property: string}>> $allInterfaces
     * @param array<string> $locales
     * @return string
     */
    private function generateMainIndex(array $allInterfaces, array $locales): string
    {
        $suffix = $this->config->getSuffix();
        $ts = "// Main index for all translation types\n\n";

        $ts .= "import type { Locale } from './shared.types';\n\n";

        // Import all module interfaces
        foreach ($allInterfaces as $sourceName => $interfaces) {
            $moduleDir = $this->toFilenameSafe($sourceName);
            $ts .= "import type { {$sourceName}{$suffix} } from './{$moduleDir}';\n";
        }

        $ts .= "\n// Combined translations interface\n";
        $ts .= "export interface {$suffix} {\n";

        foreach ($allInterfaces as $sourceName => $interfaces) {
            $propertyName = Str::camel($sourceName);
            $ts .= "  {$propertyName}: {$sourceName}{$suffix};\n";
        }

        $ts .= "}\n\n";

        $ts .= "// Localized translations\n";
        $ts .= "export type Localized{$suffix} = {\n";
        $ts .= "  [key in Locale]: {$suffix};\n";
        $ts .= "};\n\n";

        // Export all modules
        foreach ($allInterfaces as $sourceName => $interfaces) {
            $moduleDir = $this->toFilenameSafe($sourceName);
            $ts .= "export * from './{$moduleDir}';\n";
        }

        $ts .= "export * from './shared.types';\n";

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
}