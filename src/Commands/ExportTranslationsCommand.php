<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Discovery\PathsCollector;
use NVL\LaravelTypescriptTranslations\Scanners\ScannerManager;

/**
 * Command to export translation objects as JavaScript/TypeScript.
 */
class ExportTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:export
                            {--output= : Output directory path}
                            {--locale=* : Specific locales to export}
                            {--format=ts : Output format: js or ts}
                            {--module=esm : Module format: esm or commonjs}
                            {--per-locale : Export separate files per locale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export translation objects as JavaScript/TypeScript modules';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $config = app(TranslationConfig::class);
        
        $this->info('Exporting translation objects...');

        // Collect paths
        $collector = new PathsCollector($config);
        $paths = $collector->collect();

        if (empty($paths)) {
            $this->error('No translation paths found.');
            return 1;
        }

        // Scan translations for all locales separately
        $selectedLocales = $this->option('locale') ?: [];
        $allTranslations = $this->scanAllLocales($paths, $selectedLocales, $config);

        if (empty($allTranslations)) {
            $this->error('No translations found.');
            return 1;
        }

        $outputPath = $this->getOutputPath($config);
        $format = $this->option('format');
        $moduleFormat = $this->option('module');
        $perLocale = $this->option('per-locale');

        if ($perLocale) {
            $this->exportPerLocale($allTranslations, $outputPath, $format, $moduleFormat, $config);
        } else {
            $this->exportCombined($allTranslations, $outputPath, $format, $moduleFormat, $config);
        }

        $this->info("Translations exported to: {$outputPath}");

        return 0;
    }

    /**
     * Scan all locales separately.
     *
     * @param array<string, array<string>> $paths
     * @param array<string> $selectedLocales
     * @param TranslationConfig $config
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function scanAllLocales(array $paths, array $selectedLocales, TranslationConfig $config): array
    {
        $allTranslations = [];
        $scanner = new ScannerManager($config);

        // First, get all available locales
        $data = $scanner->scan($paths);
        $locales = !empty($selectedLocales) ? $selectedLocales : $data->getLocales();

        foreach ($locales as $locale) {
            $this->info("  Scanning locale: {$locale}");
            
            // Scan with base language set to this locale
            $localeConfig = $config->withOverrides(['base_language' => $locale]);
            $localeScanner = new ScannerManager($localeConfig);
            $localeData = $localeScanner->scan($paths, [$locale]);

            if (!$localeData->isEmpty()) {
                $allTranslations[$locale] = $localeData->getSources();
            }
        }

        return $allTranslations;
    }

    /**
     * Export translations per locale.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param string $outputPath
     * @param string $format
     * @param string $moduleFormat
     * @param TranslationConfig $config
     * @return void
     */
    private function exportPerLocale(array $allTranslations, string $outputPath, string $format, string $moduleFormat, TranslationConfig $config): void
    {
        $naming = $config->getTranslationNaming();
        $organization = $config->getOutputOrganization();

        foreach ($allTranslations as $locale => $sources) {
            $localeName = $this->formatLocaleName($locale, $naming['locale_format']);
            $fileName = $naming['prefix'] . $localeName . $naming['suffix'];
            
            $filePath = $outputPath;
            if ($organization['enabled']) {
                $filePath .= '/' . $organization['translations_folder'];
            }
            $filePath .= '/' . $fileName . '.' . $format;

            $content = $this->generateTranslationContent($sources, $locale, $format, $moduleFormat, $config);
            $this->writeFile($filePath, $content);
            
            $this->line("  📝 {$fileName}.{$format}");
        }

        // Generate index file
        if ($organization['enabled']) {
            $indexPath = $outputPath . '/' . $organization['translations_folder'] . '/index.' . $format;
            $indexContent = $this->generateIndexContent($allTranslations, $format, $moduleFormat, $naming);
            $this->writeFile($indexPath, $indexContent);
        }
    }

    /**
     * Export combined translations.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param string $outputPath
     * @param string $format
     * @param string $moduleFormat
     * @param TranslationConfig $config
     * @return void
     */
    private function exportCombined(array $allTranslations, string $outputPath, string $format, string $moduleFormat, TranslationConfig $config): void
    {
        $naming = $config->getTranslationNaming();
        $organization = $config->getOutputOrganization();
        
        $fileName = $naming['prefix'] . 'translations' . $naming['suffix'];
        
        $filePath = $outputPath;
        if ($organization['enabled']) {
            $filePath .= '/' . $organization['translations_folder'];
        }
        $filePath .= '/' . $fileName . '.' . $format;

        $content = $this->generateCombinedContent($allTranslations, $format, $moduleFormat, $config);
        $this->writeFile($filePath, $content);
        
        $this->line("  📝 {$fileName}.{$format}");
    }

    /**
     * Generate translation content for a locale.
     *
     * @param array<string, array<string, mixed>> $sources
     * @param string $locale
     * @param string $format
     * @param string $moduleFormat
     * @param TranslationConfig $config
     * @return string
     */
    private function generateTranslationContent(array $sources, string $locale, string $format, string $moduleFormat, TranslationConfig $config): string
    {
        $systemName = $config->getSystemTranslationsName();
        $js = "// Translation objects for locale: {$locale}\n";
        $js .= "// Generated at: " . now()->toIso8601String() . "\n\n";

        $translations = [];
        foreach ($sources as $sourceName => $files) {
            // Use configured system name if this is the system source
            if ($sourceName === 'System') {
                $sourceName = $systemName;
            }
            
            $sourceKey = Str::camel($sourceName);
            $translations[$sourceKey] = $this->processSourceFiles($files);
        }

        $jsonContent = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($format === 'ts') {
            $suffix = $config->getSuffix();
            $typeName = Str::studly($locale) . $suffix;
            
            if ($moduleFormat === 'esm') {
                $js .= "export const {$locale}Translations = {$jsonContent} as const;\n\n";
                $js .= "export type {$typeName} = typeof {$locale}Translations;\n";
            } else {
                $js .= "const {$locale}Translations = {$jsonContent} as const;\n\n";
                $js .= "type {$typeName} = typeof {$locale}Translations;\n\n";
                $js .= "module.exports = { {$locale}Translations };\n";
                $js .= "module.exports.{$typeName} = {$locale}Translations;\n";
            }
        } else {
            if ($moduleFormat === 'esm') {
                $js .= "export const {$locale}Translations = {$jsonContent};\n";
            } else {
                $js .= "const {$locale}Translations = {$jsonContent};\n\n";
                $js .= "module.exports = { {$locale}Translations };\n";
            }
        }

        return $js;
    }

    /**
     * Generate combined content.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param string $format
     * @param string $moduleFormat
     * @param TranslationConfig $config
     * @return string
     */
    private function generateCombinedContent(array $allTranslations, string $format, string $moduleFormat, TranslationConfig $config): string
    {
        $systemName = $config->getSystemTranslationsName();
        $js = "// Combined translation objects for all locales\n";
        $js .= "// Generated at: " . now()->toIso8601String() . "\n\n";

        $combined = [];
        foreach ($allTranslations as $locale => $sources) {
            $translations = [];
            foreach ($sources as $sourceName => $files) {
                // Use configured system name
                if ($sourceName === 'System') {
                    $sourceName = $systemName;
                }
                
                $sourceKey = Str::camel($sourceName);
                $translations[$sourceKey] = $this->processSourceFiles($files);
            }
            $combined[$locale] = $translations;
        }

        $jsonContent = json_encode($combined, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($format === 'ts') {
            if ($moduleFormat === 'esm') {
                $js .= "export const translations = {$jsonContent} as const;\n\n";
                $js .= "export type Translations = typeof translations;\n";
            } else {
                $js .= "const translations = {$jsonContent} as const;\n\n";
                $js .= "type Translations = typeof translations;\n\n";
                $js .= "module.exports = { translations };\n";
                $js .= "module.exports.Translations = translations;\n";
            }
        } else {
            if ($moduleFormat === 'esm') {
                $js .= "export const translations = {$jsonContent};\n";
            } else {
                $js .= "const translations = {$jsonContent};\n\n";
                $js .= "module.exports = { translations };\n";
            }
        }

        return $js;
    }

    /**
     * Generate index content.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param string $format
     * @param string $moduleFormat
     * @param array{prefix: string, suffix: string, locale_format: string} $naming
     * @return string
     */
    private function generateIndexContent(array $allTranslations, string $format, string $moduleFormat, array $naming): string
    {
        $js = "// Index file for translation exports\n\n";

        foreach (array_keys($allTranslations) as $locale) {
            $localeName = $this->formatLocaleName($locale, $naming['locale_format']);
            $fileName = $naming['prefix'] . $localeName . $naming['suffix'];
            
            if ($moduleFormat === 'esm') {
                $js .= "export * from './{$fileName}';\n";
            } else {
                $js .= "const {$locale} = require('./{$fileName}');\n";
            }
        }

        if ($moduleFormat !== 'esm') {
            $js .= "\nmodule.exports = {\n";
            foreach (array_keys($allTranslations) as $locale) {
                $js .= "  ...{$locale},\n";
            }
            $js .= "};\n";
        }

        return $js;
    }

    /**
     * Process source files to build translation object.
     *
     * @param array<string, array<string, mixed>> $files
     * @return array<string, mixed>
     */
    private function processSourceFiles(array $files): array
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
     * Format locale name based on format.
     *
     * @param string $locale
     * @param string $format
     * @return string
     */
    private function formatLocaleName(string $locale, string $format): string
    {
        return match ($format) {
            'kebab' => Str::kebab($locale),
            'camel' => Str::camel($locale),
            'studly' => Str::studly($locale),
            default => Str::snake($locale),
        };
    }

    /**
     * Get output path.
     *
     * @param TranslationConfig $config
     * @return string
     */
    private function getOutputPath(TranslationConfig $config): string
    {
        if ($this->option('output')) {
            return base_path($this->option('output'));
        }

        return base_path($config->getOutput()->getPath());
    }

    /**
     * Write file to disk.
     *
     * @param string $path
     * @param string $content
     * @return void
     */
    private function writeFile(string $path, string $content): void
    {
        $directory = dirname($path);
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, $content);
    }
}