<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Commands;

use Illuminate\Console\Command;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Enums\GenerationMode;
use NVL\LaravelTypescriptTranslations\Enums\OutputFormat;
use NVL\LaravelTypescriptTranslations\Discovery\PathsCollector;
use NVL\LaravelTypescriptTranslations\Scanners\ScannerManager;
use NVL\LaravelTypescriptTranslations\Writers\GranularWriter;
use NVL\LaravelTypescriptTranslations\Writers\ModuleWriter;
use NVL\LaravelTypescriptTranslations\Writers\SingleFileWriter;
use NVL\LaravelTypescriptTranslations\Writers\WriterInterface;

/**
 * Command to generate TypeScript type definitions from Laravel translation files.
 */
class GenerateTypesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:generate
                            {--output= : Output TypeScript file path (overrides config)}
                            {--locale=* : Specific locales to process (default: all)}
                            {--format= : Output format: nested or flat (overrides config)}
                            {--scan= : File types to scan: json, php, or all (overrides config)}
                            {--mode= : Generation mode: single, module, or granular (overrides config)}
                            {--scan-vendor : Include vendor translations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate TypeScript type definitions from Laravel language files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $config = $this->getConfiguration();
        
        $this->info('🔍 Scanning for translation files...');
        $this->displayConfiguration($config);

        // Collect paths
        $collector = new PathsCollector($config);
        $paths = $collector->collect();

        if (empty($paths)) {
            $this->error('No translation paths found.');
            return 1;
        }

        $this->info('📂 Found translation sources: ' . implode(', ', array_keys($paths)));

        // Scan translations
        $scanner = new ScannerManager($config);
        $selectedLocales = $this->option('locale') ?: [];
        $data = $scanner->scan($paths, $selectedLocales);

        if ($data->isEmpty()) {
            $this->error('No translation files found.');
            return 1;
        }

        $this->info('🌍 Found locales: ' . implode(', ', $data->getLocales()));

        // Write TypeScript files
        $writer = $this->getWriter($config);
        $writer->write($data);

        $writtenPaths = $writer->getWrittenPaths();
        $this->info('✅ TypeScript types generated successfully!');
        
        foreach ($writtenPaths as $path) {
            $this->line("   📝 {$path}");
        }

        return 0;
    }

    /**
     * Get configuration with command options.
     *
     * @return TranslationConfig
     */
    private function getConfiguration(): TranslationConfig
    {
        /** @var TranslationConfig $config */
        $config = app(TranslationConfig::class);
        
        $overrides = [];

        if ($this->option('output')) {
            $output = $this->option('output');
            if (str_ends_with($output, '.ts') || str_ends_with($output, '.d.ts')) {
                $overrides['output'] = [
                    'path' => dirname($output),
                    'filename' => basename($output),
                ];
            } else {
                $overrides['output'] = [
                    'path' => $output,
                    'filename' => 'translations.d.ts',
                ];
            }
        }

        if ($this->option('format')) {
            $overrides['format'] = $this->option('format');
        }

        if ($this->option('scan')) {
            $overrides['scan'] = $this->option('scan');
        }

        if ($this->option('mode')) {
            $overrides['mode'] = $this->option('mode');
        }

        if ($this->option('scan-vendor')) {
            $overrides['scan_vendor'] = true;
        }

        return $config->withOverrides($overrides);
    }

    /**
     * Get the appropriate writer based on configuration.
     *
     * @param TranslationConfig $config
     * @return WriterInterface
     */
    private function getWriter(TranslationConfig $config): WriterInterface
    {
        return match ($config->getMode()) {
            GenerationMode::MODULE => new ModuleWriter($config),
            GenerationMode::GRANULAR => new GranularWriter($config),
            default => new SingleFileWriter($config),
        };
    }

    /**
     * Display current configuration.
     *
     * @param TranslationConfig $config
     * @return void
     */
    private function displayConfiguration(TranslationConfig $config): void
    {
        $this->table(
            ['Setting', 'Value'],
            [
                ['Mode', $config->getMode()->value],
                ['Format', $config->getFormat()->value],
                ['Scan Types', $config->getScanTypes()],
                ['Output Path', $config->getOutput()->getFullPath()],
                ['Base Language', $config->getBaseLanguage() ?? 'All'],
                ['Export Keys', $config->shouldExportKeys() ? 'Yes' : 'No'],
                ['Scan Vendor', $config->shouldScanVendor() ? 'Yes' : 'No'],
            ]
        );
    }
}