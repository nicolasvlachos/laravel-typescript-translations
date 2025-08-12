<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Discovery\PathsCollector;
use NVL\LaravelTypescriptTranslations\Scanners\ScannerManager;
use NVL\LaravelTypescriptTranslations\Services\NamingService;
use NVL\LaravelTypescriptTranslations\Services\TranslationExportService;

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
                            {--format= : Output format: typescript, json, or both}
                            {--mode= : Export mode: single, module, or granular}
                            {--organize-by= : Organization: locale, module, or locale-mapped}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export translation objects as JavaScript/TypeScript modules';
    
    private NamingService $namingService;
    private TranslationExportService $exportService;
    
    public function __construct()
    {
        parent::__construct();
        $this->namingService = new NamingService();
        $this->exportService = new TranslationExportService($this->namingService);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $config = app(TranslationConfig::class);
        
        $this->info('🔍 Exporting translation objects...');

        // Get export configuration
        $exportConfig = $this->getExportConfig($config);
        $this->displayExportConfiguration($exportConfig);

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

        $this->info('🌍 Found locales: ' . implode(', ', array_keys($allTranslations)));

        // Export based on configuration
        $this->exportTranslations($allTranslations, $exportConfig, $config);

        $this->info("✅ Translations exported to: {$exportConfig['path']}");

        return 0;
    }

    /**
     * Get export configuration from config and options.
     *
     * @param TranslationConfig $config
     * @return array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string}
     */
    private function getExportConfig(TranslationConfig $config): array
    {
        $exportConfig = config('typescript-translations.translation_export', [
            'path' => 'resources/js/data/translations',
            'mode' => 'module',
            'organize_by' => 'locale',
            'format' => 'typescript',
            'filename_pattern' => '{locale}.ts'
        ]);

        // Override with command options
        if ($this->option('output')) {
            $exportConfig['path'] = $this->option('output');
        }
        if ($this->option('format')) {
            $exportConfig['format'] = $this->option('format');
        }
        if ($this->option('mode')) {
            $exportConfig['mode'] = $this->option('mode');
        }
        if ($this->option('organize-by')) {
            $exportConfig['organize_by'] = $this->option('organize-by');
        }

        // Convert relative path to absolute
        $exportConfig['path'] = base_path($exportConfig['path']);

        return $exportConfig;
    }

    /**
     * Display export configuration.
     *
     * @param array{path: string, mode: string, organize_by: string, format: string} $exportConfig
     * @return void
     */
    private function displayExportConfiguration(array $exportConfig): void
    {
        $this->table(
            ['Setting', 'Value'],
            [
                ['Export Path', $exportConfig['path']],
                ['Mode', $exportConfig['mode']],
                ['Organization', $exportConfig['organize_by']],
                ['Format', $exportConfig['format']],
            ]
        );
    }

    /**
     * Export translations based on configuration.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportTranslations(array $allTranslations, array $exportConfig, TranslationConfig $config): void
    {
        if ($exportConfig['organize_by'] === 'locale') {
            $this->exportByLocale($allTranslations, $exportConfig, $config);
        } elseif ($exportConfig['organize_by'] === 'locale-mapped') {
            $this->exportByLocaleMapped($allTranslations, $exportConfig, $config);
        } else {
            $this->exportByModule($allTranslations, $exportConfig, $config);
        }
    }

    /**
     * Export translations organized by locale.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportByLocale(array $allTranslations, array $exportConfig, TranslationConfig $config): void
    {
        foreach ($allTranslations as $locale => $sources) {
            $localePath = $exportConfig['path'] . '/' . $locale;
            
            if ($exportConfig['mode'] === 'single') {
                // Single file per locale
                $this->exportLocaleSingleFile($locale, $sources, $localePath, $exportConfig, $config);
            } elseif ($exportConfig['mode'] === 'module') {
                // Module files per locale
                $this->exportLocaleModules($locale, $sources, $localePath, $exportConfig, $config);
            } else {
                // Granular files per locale
                $this->exportLocaleGranular($locale, $sources, $localePath, $exportConfig, $config);
            }
        }

        // Generate main index file
        $this->generateMainIndex($allTranslations, $exportConfig);
    }

    /**
     * Export translations as locale-mapped objects.
     * Each module exports an object with all locales.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportByLocaleMapped(array $allTranslations, array $exportConfig, TranslationConfig $config): void
    {
        if ($exportConfig['mode'] === 'granular') {
            // Use the exact same logic as GranularWriter for types
            $this->exportGranularLocaleMapped($allTranslations, $exportConfig, $config);
        } elseif ($exportConfig['mode'] === 'module') {
            // Module mode - one file per source but with separate exports for each translation file
            $modulesBySource = [];
            
            // Organize data by source and file
            foreach ($allTranslations as $locale => $sources) {
                foreach ($sources as $sourceName => $files) {
                    if (!isset($modulesBySource[$sourceName])) {
                        $modulesBySource[$sourceName] = [];
                    }
                    
                    foreach ($files as $file => $translations) {
                        // Create separate export for each file
                        if ($file === '_json') {
                            $exportName = Str::studly($sourceName);
                        } else {
                            // Use buildModuleName to handle dots properly
                            $exportName = $this->buildModuleName($sourceName, $file);
                        }
                        
                        if (!isset($modulesBySource[$sourceName][$exportName])) {
                            $modulesBySource[$sourceName][$exportName] = [];
                        }
                        
                        $modulesBySource[$sourceName][$exportName][$locale] = $translations;
                    }
                }
            }
            
            // Export each source as a single file with multiple exports
            foreach ($modulesBySource as $sourceName => $exports) {
                $filePath = $exportConfig['path'] . '/' . $this->toFilenameSafe($sourceName) . '.translations.' . 
                           $this->getFileExtension($exportConfig['format']);
                
                $content = $this->generateModuleFileContent($sourceName, $exports, $exportConfig['format']);
                $this->writeFile($filePath, $content);
                
                $this->line("  📝 " . str_replace($exportConfig['path'] . '/', '', $filePath));
            }
            
            // Generate index file
            $this->generateModuleIndexForFlatStructure($modulesBySource, $exportConfig);
        } else {
            // Single mode - combine everything into one file
            $moduleData = [];
            
            foreach ($allTranslations as $locale => $sources) {
                $combinedTranslations = [];
                foreach ($sources as $sourceName => $files) {
                    $combinedTranslations[$sourceName] = $this->processSourceFiles($files);
                }
                $moduleData['translations'][$locale] = $combinedTranslations;
            }
            
            // Export as single file
            $filePath = $exportConfig['path'] . '/translations.' . $this->getFileExtension($exportConfig['format']);
            $content = $this->generateLocaleMappedContent('Translations', $moduleData['translations'], $exportConfig['format']);
            $this->writeFile($filePath, $content);
            
            $this->line("  📝 translations.{$this->getFileExtension($exportConfig['format'])}");
        }
    }
    
    /**
     * Generate content for locale-mapped format.
     * Delegates to the export service.
     *
     * @param string $moduleName
     * @param array<string, mixed> $localeData
     * @param string $format
     * @return string
     */
    private function generateLocaleMappedContent(string $moduleName, array $localeData, string $format): string
    {
        if ($format === 'json') {
            return json_encode($localeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        return $this->exportService->generateLocaleMappedContent($moduleName, $localeData);
    }
    
    /**
     * Generate index for locale-mapped exports.
     *
     * @param array<string, array<string, mixed>> $moduleData
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @return void
     */
    private function generateLocaleMappedIndex(array $moduleData, array $exportConfig): void
    {
        $ext = $this->getFileExtension($exportConfig['format']);
        $indexPath = $exportConfig['path'] . '/index.' . $ext;
        
        $content = "/**\n";
        $content .= " * Main index file for all translation modules\n";
        $content .= " * Generated at: " . now()->toIso8601String() . "\n";
        $content .= " */\n\n";
        
        if ($exportConfig['format'] === 'typescript') {
            // Export based on mode
            if ($exportConfig['mode'] === 'granular') {
                // Granular mode - organize by source folders
                $modulesBySource = [];
                
                foreach (array_keys($moduleData) as $moduleName) {
                    // Extract source name from module pattern
                    $sourceName = null;
                    if (preg_match('/^([A-Z][a-zA-Z]+?)(?:[A-Z]|$)/', $moduleName, $matches)) {
                        $sourceName = Str::kebab(strtolower($matches[1]));
                    }
                    
                    if (!$sourceName) {
                        $sourceName = 'default';
                    }
                    
                    if (!isset($modulesBySource[$sourceName])) {
                        $modulesBySource[$sourceName] = [];
                    }
                    $modulesBySource[$sourceName][] = $moduleName;
                }
                
                // Export grouped by source
                foreach ($modulesBySource as $source => $modules) {
                    if ($source !== 'default') {
                        $content .= "// {$source} translations\n";
                    }
                    
                    foreach ($modules as $moduleName) {
                        $studlySource = Str::studly($source);
                        $exportName = $moduleName . 'Translations';
                        
                        // Determine import path
                        if (str_starts_with($moduleName, $studlySource)) {
                            $filePart = Str::after($moduleName, $studlySource);
                            
                            if ($filePart === '') {
                                // Just source name - import from folder index
                                $importPath = './' . $this->toFilenameSafe($source) . '/index';
                            } else {
                                // File in folder
                                $fileName = $this->toFilenameSafe($filePart);
                                $importPath = './' . $this->toFilenameSafe($source) . '/' . $fileName;
                            }
                        } else {
                            // Fallback
                            $importPath = './' . $this->toFilenameSafe($moduleName);
                        }
                        
                        $content .= "export { {$exportName} } from '{$importPath}';\n";
                        $content .= "export type { {$exportName}Type, {$moduleName}Locales } from '{$importPath}';\n";
                    }
                    
                    if ($source !== 'default') {
                        $content .= "\n";
                    }
                }
            } else {
                // Module or single mode - flat structure
                foreach (array_keys($moduleData) as $moduleName) {
                    $exportName = $moduleName . 'Translations';
                    $fileName = $this->toFilenameSafe($moduleName);
                    $importPath = './' . $fileName;
                    
                    $content .= "export { {$exportName} } from '{$importPath}';\n";
                    $content .= "export type { {$exportName}Type, {$moduleName}Locales } from '{$importPath}';\n";
                }
            }
        }
        
        $this->writeFile($indexPath, $content);
    }

    /**
     * Export translations organized by module.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportByModule(array $allTranslations, array $exportConfig, TranslationConfig $config): void
    {
        // Reorganize data by module instead of locale
        $moduleData = [];
        foreach ($allTranslations as $locale => $sources) {
            foreach ($sources as $sourceName => $files) {
                if (!isset($moduleData[$sourceName])) {
                    $moduleData[$sourceName] = [];
                }
                $moduleData[$sourceName][$locale] = $files;
            }
        }

        foreach ($moduleData as $moduleName => $locales) {
            $modulePath = $exportConfig['path'] . '/' . $this->toFilenameSafe($moduleName);
            
            if ($exportConfig['mode'] === 'single') {
                $this->exportModuleSingleFile($moduleName, $locales, $modulePath, $exportConfig, $config);
            } else {
                $this->exportModuleByLocale($moduleName, $locales, $modulePath, $exportConfig, $config);
            }
        }

        // Generate main index file
        $this->generateMainIndex($allTranslations, $exportConfig);
    }

    /**
     * Convert name to filename-safe format.
     * Delegates to the naming service.
     *
     * @param string $name
     * @return string
     */
    private function toFilenameSafe(string $name): string
    {
        return $this->namingService->toFilenameSafe($name);
    }

    /**
     * Build module name matching type generation logic.
     * Delegates to the naming service.
     *
     * @param string $sourceName
     * @param string $file
     * @return string
     */
    private function buildModuleName(string $sourceName, string $file): string
    {
        return $this->namingService->buildModuleName($sourceName, $file);
    }
    
    /**
     * Export granular locale-mapped translations matching type generation structure.
     * This mirrors the exact logic from GranularWriter.php
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportGranularLocaleMapped(array $allTranslations, array $exportConfig, TranslationConfig $config): void
    {
        $allModuleData = [];
        
        // Process each source and create the exact folder structure as type generation
        foreach ($allTranslations as $locale => $sources) {
            foreach ($sources as $sourceName => $files) {
                foreach ($files as $file => $translations) {
                    $isJson = ($file === '_json');
                    
                    // Build interface name exactly like GranularWriter does
                    if ($isJson) {
                        $interfaceName = Str::studly($sourceName);
                        $fileName = 'index'; // JSON goes to index file
                    } else {
                        // Handle dots in file names (bank-accounts.pages -> BankAccountsPages)
                        $interfaceName = $this->buildModuleName($sourceName, $file);
                        // Just use the file name, not prefixed with source name
                        $fileName = Str::kebab(strtolower(str_replace('.', '-', $file)));
                    }
                    
                    // Store data for this module
                    if (!isset($allModuleData[$sourceName])) {
                        $allModuleData[$sourceName] = [];
                    }
                    
                    if (!isset($allModuleData[$sourceName][$fileName])) {
                        $allModuleData[$sourceName][$fileName] = [
                            'interfaceName' => $interfaceName,
                            'data' => [],
                            'isJson' => $isJson,
                            'file' => $file
                        ];
                    }
                    
                    $allModuleData[$sourceName][$fileName]['data'][$locale] = $translations;
                }
            }
        }
        
        // Write files in the exact same structure as GranularWriter
        foreach ($allModuleData as $sourceName => $moduleFiles) {
            $moduleDir = $exportConfig['path'] . '/' . $this->toFilenameSafe($sourceName);
            
            $moduleExports = [];
            
            foreach ($moduleFiles as $fileName => $fileData) {
                $filePath = $moduleDir . '/' . $fileName . '.' . $this->getFileExtension($exportConfig['format']);
                
                // Generate content for this file
                $content = $this->generateLocaleMappedContent($fileData['interfaceName'], $fileData['data'], $exportConfig['format']);
                $this->writeFile($filePath, $content);
                
                $this->line("  📝 " . str_replace($exportConfig['path'] . '/', '', $filePath));
                
                $moduleExports[] = [
                    'name' => $fileData['interfaceName'],
                    'fileName' => $fileName,
                    'isJson' => $fileData['isJson']
                ];
            }
            
            // Generate module index file
            $this->generateModuleIndexFile($sourceName, $moduleExports, $moduleDir, $exportConfig);
        }
        
        // Generate main index
        $this->generateGranularMainIndex($allModuleData, $exportConfig);
    }
    
    /**
     * Generate module index file for granular exports.
     */
    private function generateModuleIndexFile(string $sourceName, array $exports, string $moduleDir, array $exportConfig): void
    {
        $ext = $this->getFileExtension($exportConfig['format']);
        
        // Check if we only have index (JSON) exports
        $hasOnlyIndex = count($exports) === 1 && $exports[0]['fileName'] === 'index';
        
        if ($hasOnlyIndex) {
            // Don't create a separate index file if we only have JSON translations
            // The index.ts already contains the exports
            return;
        }
        
        $indexPath = $moduleDir . '/index.' . $ext;
        
        $content = "// Module index for {$sourceName} translations\n\n";
        
        // Export all module files
        foreach ($exports as $export) {
            $exportName = $export['name'] . 'Translations';
            $content .= "export { {$exportName} } from './{$export['fileName']}';\n";
            $content .= "export type { {$exportName}Type, {$export['name']}Locales } from './{$export['fileName']}';\n";
        }
        
        $this->writeFile($indexPath, $content);
    }
    
    /**
     * Generate main index for granular exports.
     * Delegates to the export service.
     */
    private function generateGranularMainIndex(array $allModuleData, array $exportConfig): void
    {
        $this->exportService->generateGranularIndex($allModuleData, $exportConfig['path'], $exportConfig['format']);
    }
    
    /**
     * Generate content for a module file with multiple exports.
     * Delegates to the export service.
     */
    private function generateModuleFileContent(string $sourceName, array $exports, string $format): string
    {
        if ($format === 'json') {
            // For JSON, we need to structure it differently
            $allExports = [];
            foreach ($exports as $exportName => $localeData) {
                $allExports[$exportName] = $localeData;
            }
            return json_encode($allExports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        return $this->exportService->generateModuleFileContent($sourceName, $exports);
    }
    
    /**
     * Generate index for module mode (flat structure with multiple exports per file).
     */
    private function generateModuleIndexForFlatStructure(array $modulesBySource, array $exportConfig): void
    {
        $ext = $this->getFileExtension($exportConfig['format']);
        $indexPath = $exportConfig['path'] . '/index.' . $ext;
        
        $content = "/**\n";
        $content .= " * Main index for all translation modules\n";
        $content .= " * Generated at: " . now()->toIso8601String() . "\n";
        $content .= " */\n\n";
        
        if ($exportConfig['format'] === 'typescript') {
            // Export all from each module file
            foreach ($modulesBySource as $sourceName => $exports) {
                $fileName = $this->toFilenameSafe($sourceName) . '.translations';
                $content .= "// {$sourceName} translations\n";
                
                // Export all named exports from the module file
                foreach ($exports as $exportName => $data) {
                    $translationsName = $exportName . 'Translations';
                    $content .= "export { {$translationsName} } from './{$fileName}';\n";
                    $content .= "export type { {$translationsName}Type, {$exportName}Locales } from './{$fileName}';\n";
                }
                
                $content .= "\n";
            }
        }
        
        $this->writeFile($indexPath, $content);
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
     * Export single file for a locale.
     *
     * @param string $locale
     * @param array<string, array<string, mixed>> $sources
     * @param string $path
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportLocaleSingleFile(string $locale, array $sources, string $path, array $exportConfig, TranslationConfig $config): void
    {
        $filename = 'index.' . $this->getFileExtension($exportConfig['format']);
        $filePath = $path . '/' . $filename;
        
        $translations = [];
        foreach ($sources as $sourceName => $files) {
            $sourceKey = Str::camel($sourceName);
            $translations[$sourceKey] = $this->processSourceFiles($files);
        }
        
        $content = $this->generateFileContent($translations, $locale, $exportConfig['format']);
        $this->writeFile($filePath, $content);
        
        $this->line("  📝 {$locale}/{$filename}");
    }

    /**
     * Export module files for a locale.
     *
     * @param string $locale
     * @param array<string, array<string, mixed>> $sources
     * @param string $path
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportLocaleModules(string $locale, array $sources, string $path, array $exportConfig, TranslationConfig $config): void
    {
        // When using locale-mapped export, create a different structure
        if ($exportConfig['organize_by'] === 'locale-mapped') {
            // This will be handled by exportByLocaleMapped method
            return;
        }
        
        foreach ($sources as $sourceName => $files) {
            $filename = $this->toFilenameSafe($sourceName) . '.' . $this->getFileExtension($exportConfig['format']);
            $filePath = $path . '/' . $filename;
            
            $translations = $this->processSourceFiles($files);
            $content = $this->generateFileContent($translations, "{$locale}_{$sourceName}", $exportConfig['format']);
            $this->writeFile($filePath, $content);
            
            $this->line("  📝 {$locale}/{$filename}");
        }
        
        // Generate index for this locale
        $this->generateLocaleIndex($locale, $sources, $path, $exportConfig);
    }

    /**
     * Export granular files for a locale.
     *
     * @param string $locale
     * @param array<string, array<string, mixed>> $sources
     * @param string $path
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportLocaleGranular(string $locale, array $sources, string $path, array $exportConfig, TranslationConfig $config): void
    {
        foreach ($sources as $sourceName => $files) {
            $modulePath = $path . '/' . $this->toFilenameSafe($sourceName);
            
            foreach ($files as $file => $translations) {
                if ($file === '_json') {
                    $filename = 'json.' . $this->getFileExtension($exportConfig['format']);
                } else {
                    $filename = $this->toFilenameSafe($file) . '.' . $this->getFileExtension($exportConfig['format']);
                }
                
                $filePath = $modulePath . '/' . $filename;
                $content = $this->generateFileContent($translations, "{$locale}_{$sourceName}_{$file}", $exportConfig['format']);
                $this->writeFile($filePath, $content);
                
                $this->line("  📝 {$locale}/{$this->toFilenameSafe($sourceName)}/{$filename}");
            }
        }
        
        // Generate index for this locale
        $this->generateLocaleIndex($locale, $sources, $path, $exportConfig);
    }

    /**
     * Generate index file for a locale.
     *
     * @param string $locale
     * @param array<string, array<string, mixed>> $sources
     * @param string $path
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @return void
     */
    private function generateLocaleIndex(string $locale, array $sources, string $path, array $exportConfig): void
    {
        $ext = $this->getFileExtension($exportConfig['format']);
        $indexPath = $path . '/index.' . $ext;
        
        $content = "// Index file for {$locale} translations\n\n";
        
        if ($exportConfig['format'] === 'typescript' || $exportConfig['format'] === 'both') {
            foreach ($sources as $sourceName => $files) {
                $moduleFile = './' . $this->toFilenameSafe($sourceName);
                $content .= "export * from '{$moduleFile}';\n";
            }
        }
        
        $this->writeFile($indexPath, $content);
    }

    /**
     * Generate main index file.
     *
     * @param array<string, array<string, array<string, mixed>>> $allTranslations
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @return void
     */
    private function generateMainIndex(array $allTranslations, array $exportConfig): void
    {
        $ext = $this->getFileExtension($exportConfig['format']);
        $indexPath = $exportConfig['path'] . '/index.' . $ext;
        
        $content = "// Main index file for all translations\n\n";
        
        if ($exportConfig['organize_by'] === 'locale') {
            foreach (array_keys($allTranslations) as $locale) {
                $content .= "export * as {$locale} from './{$locale}';\n";
            }
        } else {
            // Module organization
            $modules = [];
            foreach ($allTranslations as $locale => $sources) {
                foreach (array_keys($sources) as $sourceName) {
                    $modules[$sourceName] = true;
                }
            }
            
            foreach (array_keys($modules) as $module) {
                $moduleKey = Str::camel($module);
                $content .= "export * as {$moduleKey} from './" . $this->toFilenameSafe($module) . "';\n";
            }
        }
        
        $this->writeFile($indexPath, $content);
    }

    /**
     * Get file extension based on format.
     * Delegates to the naming service.
     *
     * @param string $format
     * @return string
     */
    private function getFileExtension(string $format): string
    {
        return $this->namingService->getFileExtension($format);
    }

    /**
     * Generate file content based on format.
     *
     * @param array<string, mixed> $translations
     * @param string $name
     * @param string $format
     * @return string
     */
    private function generateFileContent(array $translations, string $name, string $format): string
    {
        $safeName = Str::camel($name);
        $jsonContent = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($format === 'typescript') {
            $content = "// Auto-generated translation file\n";
            $content .= "// Generated at: " . now()->toIso8601String() . "\n\n";
            $content .= "export const {$safeName} = {$jsonContent} as const;\n\n";
            $content .= "export type " . Str::studly($name) . " = typeof {$safeName};\n";
            return $content;
        } elseif ($format === 'json') {
            return $jsonContent;
        } else {
            // Both
            $content = "// Auto-generated translation file\n";
            $content .= "// Generated at: " . now()->toIso8601String() . "\n\n";
            $content .= "export const {$safeName} = {$jsonContent} as const;\n\n";
            $content .= "export type " . Str::studly($name) . " = typeof {$safeName};\n";
            
            // Also write JSON file
            $jsonPath = str_replace('.ts', '.json', $name);
            $this->writeFile($jsonPath, $jsonContent);
            
            return $content;
        }
    }

    /**
     * Export single file for a module.
     *
     * @param string $moduleName
     * @param array<string, array<string, mixed>> $locales
     * @param string $path
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportModuleSingleFile(string $moduleName, array $locales, string $path, array $exportConfig, TranslationConfig $config): void
    {
        $filename = 'index.' . $this->getFileExtension($exportConfig['format']);
        $filePath = $path . '/' . $filename;
        
        $translations = [];
        foreach ($locales as $locale => $files) {
            $translations[$locale] = $this->processSourceFiles($files);
        }
        
        $content = $this->generateFileContent($translations, $moduleName, $exportConfig['format']);
        $this->writeFile($filePath, $content);
        
        $this->line("  📝 {$this->toFilenameSafe($moduleName)}/{$filename}");
    }

    /**
     * Export module organized by locale.
     *
     * @param string $moduleName
     * @param array<string, array<string, mixed>> $locales
     * @param string $path
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @param TranslationConfig $config
     * @return void
     */
    private function exportModuleByLocale(string $moduleName, array $locales, string $path, array $exportConfig, TranslationConfig $config): void
    {
        foreach ($locales as $locale => $files) {
            $filename = $locale . '.' . $this->getFileExtension($exportConfig['format']);
            $filePath = $path . '/' . $filename;
            
            $translations = $this->processSourceFiles($files);
            $content = $this->generateFileContent($translations, "{$moduleName}_{$locale}", $exportConfig['format']);
            $this->writeFile($filePath, $content);
            
            $this->line("  📝 {$this->toFilenameSafe($moduleName)}/{$filename}");
        }
        
        // Generate module index
        $this->generateModuleIndex($moduleName, $locales, $path, $exportConfig);
    }

    /**
     * Generate index file for a module.
     *
     * @param string $moduleName
     * @param array<string, array<string, mixed>> $locales
     * @param string $path
     * @param array{path: string, mode: string, organize_by: string, format: string, filename_pattern: string} $exportConfig
     * @return void
     */
    private function generateModuleIndex(string $moduleName, array $locales, string $path, array $exportConfig): void
    {
        $ext = $this->getFileExtension($exportConfig['format']);
        $indexPath = $path . '/index.' . $ext;
        
        $content = "// Index file for {$moduleName} module\n\n";
        
        if ($exportConfig['format'] === 'typescript' || $exportConfig['format'] === 'both') {
            foreach (array_keys($locales) as $locale) {
                $content .= "export * as {$locale} from './{$locale}';\n";
            }
        }
        
        $this->writeFile($indexPath, $content);
    }

    /**
     * Process source files to build translation object.
     * Delegates to the export service.
     *
     * @param array<string, array<string, mixed>> $files
     * @return array<string, mixed>
     */
    private function processSourceFiles(array $files): array
    {
        return $this->exportService->processSourceFiles($files);
    }


    /**
     * Write file to disk.
     * Delegates to the export service.
     *
     * @param string $path
     * @param string $content
     * @return void
     */
    private function writeFile(string $path, string $content): void
    {
        $this->exportService->writeFile($path, $content);
    }
}