<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Writers;

use Illuminate\Support\Str;
use NVL\LaravelTypescriptTranslations\Data\TranslationData;

/**
 * Writes all translations to a single TypeScript file.
 */
class SingleFileWriter extends BaseWriter
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
        $outputPath = $outputConfig->getFullPath();

        $content = $this->generateContent($data);
        $this->writeFile($outputPath, $content);
    }

    /**
     * Generate TypeScript content.
     *
     * @param TranslationData $data
     * @return string
     */
    private function generateContent(TranslationData $data): string
    {
        $ts = $this->generator->generateHeader();
        $suffix = $this->config->getSuffix();
        $sources = $data->getSources();
        $locales = $data->getLocales();

        // Generate interface for each source/module
        foreach ($sources as $sourceName => $structure) {
            if (empty($structure)) {
                continue;
            }

            $interfaceName = $sourceName . $suffix;
            $ts .= "export interface {$interfaceName} {\n";

            foreach ($structure as $file => $translations) {
                if ($file === '_json') {
                    // Inline JSON translations at root level
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
                    // For PHP files, create nested structure
                    $safeFile = $this->getSafeKey($file);
                    $ts .= "  {$safeFile}: {\n";
                    $ts .= $this->generator->generateStructure($translations, 4);
                    $ts .= "  };\n";
                }
            }

            $ts .= "}\n\n";
        }

        // Generate combined translations interface
        $ts .= "export interface {$suffix} {\n";
        foreach ($sources as $sourceName => $structure) {
            if (empty($structure)) {
                continue;
            }
            $interfaceName = $sourceName . $suffix;
            $propertyName = Str::camel($sourceName);
            $ts .= "  {$propertyName}: {$interfaceName};\n";
        }
        $ts .= "}\n\n";

        // Generate locale type
        if (!empty($locales)) {
            $localeUnion = implode(' | ', array_map(fn($l) => "'{$l}'", $locales));
            $ts .= $this->stubManager->get('locale-type.stub', [
                'locales' => $localeUnion
            ]) . "\n\n";
        }

        // Generate localized translations type
        $ts .= "export type Localized{$suffix} = {\n";
        $ts .= "  [key in Locale]: {$suffix};\n";
        $ts .= "};\n\n";

        // Generate translation keys if enabled
        if ($this->config->shouldExportKeys()) {
            foreach ($sources as $sourceName => $structure) {
                if (empty($structure)) {
                    continue;
                }
                
                $keyTypeName = $sourceName . 'TranslationKey';
                $keys = $this->generator->generateKeys($structure);
                
                $ts .= "// Translation keys for {$sourceName}\n";
                $ts .= $this->generator->generateKeysType($keys, $keyTypeName);
                $ts .= "\n";
            }

            // Generate combined keys type
            $ts .= "// Union of all translation keys\n";
            $ts .= "export type TranslationKey = ";
            
            $allKeys = [];
            foreach ($sources as $sourceName => $structure) {
                if (empty($structure)) {
                    continue;
                }
                $propertyName = Str::camel($sourceName);
                $keys = $this->generator->generateKeys($structure);
                foreach ($keys as $key) {
                    $allKeys[] = "'{$propertyName}.{$key}'";
                }
            }
            
            if (!empty($allKeys)) {
                $ts .= implode(' | ', $allKeys) . ";\n";
            } else {
                $ts .= "never;\n";
            }
        }

        return $ts;
    }
}