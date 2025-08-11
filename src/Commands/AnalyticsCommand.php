<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Discovery\PathsCollector;
use NVL\LaravelTypescriptTranslations\Scanners\ScannerManager;

/**
 * Command to display detailed analytics about translations.
 */
class AnalyticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:analytics
                            {--json : Output as JSON}
                            {--detailed : Show detailed breakdown}
                            {--scan-vendor : Include vendor translations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display detailed analytics about translation files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $config = $this->getConfiguration();
        
        $this->info('📊 Analyzing translation files...');
        $this->newLine();

        // Collect paths
        $collector = new PathsCollector($config);
        $paths = $collector->collect();

        if (empty($paths)) {
            $this->error('No translation paths found.');
            return 1;
        }

        // Scan translations
        $scanner = new ScannerManager($config);
        $data = $scanner->scan($paths);

        if ($data->isEmpty()) {
            $this->error('No translation files found.');
            return 1;
        }

        $analytics = $this->calculateAnalytics($paths, $data);

        if ($this->option('json')) {
            $this->outputJson($analytics);
        } else {
            $this->outputAnalytics($analytics);
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

        if ($this->option('scan-vendor')) {
            $overrides['scan_vendor'] = true;
        }

        return $config->withOverrides($overrides);
    }

    /**
     * Calculate analytics.
     *
     * @param array<string, array<string>> $paths
     * @param \NicolasVlachos\LaravelTypescriptTranslations\Data\TranslationData $data
     * @return array<string, mixed>
     */
    private function calculateAnalytics(array $paths, $data): array
    {
        $analytics = [
            'overview' => [
                'total_paths' => 0,
                'total_files' => 0,
                'total_keys' => 0,
                'total_sources' => count($data->getSources()),
                'total_locales' => count($data->getLocales()),
            ],
            'sources' => [],
            'locales' => $data->getLocales(),
            'file_types' => [
                'php' => 0,
                'json' => 0,
            ],
            'paths_list' => [],
        ];

        // Count paths
        foreach ($paths as $source => $sourcePaths) {
            $analytics['overview']['total_paths'] += count($sourcePaths);
            foreach ($sourcePaths as $path) {
                $analytics['paths_list'][] = [
                    'source' => $source,
                    'path' => $path,
                    'exists' => File::exists($path),
                ];
                
                // Count files in path
                if (File::exists($path)) {
                    $phpFiles = $this->countFiles($path, '*.php');
                    $jsonFiles = $this->countFiles($path, '*.json');
                    
                    $analytics['file_types']['php'] += $phpFiles;
                    $analytics['file_types']['json'] += $jsonFiles;
                    $analytics['overview']['total_files'] += $phpFiles + $jsonFiles;
                }
            }
        }

        // Analyze sources
        foreach ($data->getSources() as $source => $files) {
            $sourceAnalytics = [
                'name' => $source,
                'files_count' => count($files),
                'keys_count' => 0,
                'files' => [],
            ];

            foreach ($files as $file => $translations) {
                $keyCount = $this->countKeysRecursive($translations);
                $sourceAnalytics['keys_count'] += $keyCount;
                $analytics['overview']['total_keys'] += $keyCount;

                if ($this->option('detailed')) {
                    $sourceAnalytics['files'][$file] = [
                        'keys' => $keyCount,
                        'type' => str_starts_with($file, '_json') ? 'json' : 'php',
                    ];
                }
            }

            $analytics['sources'][] = $sourceAnalytics;
        }

        // Calculate averages
        $analytics['statistics'] = [
            'avg_keys_per_file' => $analytics['overview']['total_files'] > 0 
                ? round($analytics['overview']['total_keys'] / $analytics['overview']['total_files'], 2)
                : 0,
            'avg_keys_per_source' => $analytics['overview']['total_sources'] > 0
                ? round($analytics['overview']['total_keys'] / $analytics['overview']['total_sources'], 2)
                : 0,
            'php_vs_json_ratio' => $analytics['file_types']['json'] > 0
                ? round($analytics['file_types']['php'] / $analytics['file_types']['json'], 2)
                : $analytics['file_types']['php'],
        ];

        return $analytics;
    }

    /**
     * Count files in a directory recursively.
     *
     * @param string $path
     * @param string $pattern
     * @return int
     */
    private function countFiles(string $path, string $pattern): int
    {
        $count = 0;
        
        // Count in locale directories
        $locales = File::directories($path);
        foreach ($locales as $locale) {
            $files = File::glob($locale . '/' . $pattern);
            $count += count($files);
            
            // Count in subdirectories
            $subdirs = File::directories($locale);
            foreach ($subdirs as $subdir) {
                $count += $this->countFilesRecursive($subdir, $pattern);
            }
        }
        
        // Count root files (for JSON)
        if ($pattern === '*.json') {
            $rootFiles = File::glob($path . '/' . $pattern);
            $count += count($rootFiles);
        }
        
        return $count;
    }

    /**
     * Count files recursively.
     *
     * @param string $directory
     * @param string $pattern
     * @return int
     */
    private function countFilesRecursive(string $directory, string $pattern): int
    {
        $count = count(File::glob($directory . '/' . $pattern));
        
        $subdirs = File::directories($directory);
        foreach ($subdirs as $subdir) {
            $count += $this->countFilesRecursive($subdir, $pattern);
        }
        
        return $count;
    }

    /**
     * Count keys recursively.
     *
     * @param array<string, mixed> $array
     * @return int
     */
    private function countKeysRecursive(array $array): int
    {
        $count = 0;
        
        foreach ($array as $value) {
            if (is_array($value)) {
                $count += $this->countKeysRecursive($value);
            } else {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Output analytics as JSON.
     *
     * @param array<string, mixed> $analytics
     * @return void
     */
    private function outputJson(array $analytics): void
    {
        $this->line(json_encode($analytics, JSON_PRETTY_PRINT));
    }

    /**
     * Output analytics in readable format.
     *
     * @param array<string, mixed> $analytics
     * @return void
     */
    private function outputAnalytics(array $analytics): void
    {
        // Overview
        $this->components->twoColumnDetail('<fg=cyan;options=bold>OVERVIEW</>');
        $this->components->twoColumnDetail('Total Paths', $analytics['overview']['total_paths']);
        $this->components->twoColumnDetail('Total Files', $analytics['overview']['total_files']);
        $this->components->twoColumnDetail('Total Keys', number_format($analytics['overview']['total_keys']));
        $this->components->twoColumnDetail('Total Sources', $analytics['overview']['total_sources']);
        $this->components->twoColumnDetail('Total Locales', $analytics['overview']['total_locales']);
        $this->newLine();

        // File Types
        $this->components->twoColumnDetail('<fg=cyan;options=bold>FILE TYPES</>');
        $this->components->twoColumnDetail('PHP Files', $analytics['file_types']['php']);
        $this->components->twoColumnDetail('JSON Files', $analytics['file_types']['json']);
        $this->newLine();

        // Statistics
        $this->components->twoColumnDetail('<fg=cyan;options=bold>STATISTICS</>');
        $this->components->twoColumnDetail('Avg Keys/File', $analytics['statistics']['avg_keys_per_file']);
        $this->components->twoColumnDetail('Avg Keys/Source', $analytics['statistics']['avg_keys_per_source']);
        $this->components->twoColumnDetail('PHP/JSON Ratio', $analytics['statistics']['php_vs_json_ratio']);
        $this->newLine();

        // Locales
        $this->components->twoColumnDetail('<fg=cyan;options=bold>LOCALES</>');
        $this->components->twoColumnDetail('Available', implode(', ', $analytics['locales']));
        $this->newLine();

        // Sources breakdown
        $this->info('📦 Sources Breakdown:');
        $tableData = [];
        foreach ($analytics['sources'] as $source) {
            $tableData[] = [
                $source['name'],
                $source['files_count'],
                number_format($source['keys_count']),
            ];
        }
        
        $this->table(
            ['Source', 'Files', 'Keys'],
            $tableData
        );

        // Detailed file breakdown if requested
        if ($this->option('detailed')) {
            $this->newLine();
            $this->info('📄 Detailed File Breakdown:');
            
            foreach ($analytics['sources'] as $source) {
                if (empty($source['files'])) {
                    continue;
                }
                
                $this->line("<comment>{$source['name']}:</comment>");
                foreach ($source['files'] as $file => $details) {
                    $this->line("  - {$file}: {$details['keys']} keys ({$details['type']})");
                }
            }
        }

        // Paths list
        if ($this->option('detailed')) {
            $this->newLine();
            $this->info('📁 Discovered Paths:');
            
            foreach ($analytics['paths_list'] as $pathInfo) {
                $status = $pathInfo['exists'] ? '✓' : '✗';
                $color = $pathInfo['exists'] ? 'green' : 'red';
                $this->line("  <fg={$color}>[{$status}]</> {$pathInfo['source']}: {$pathInfo['path']}");
            }
        }

        // Summary
        $this->newLine();
        $this->components->info(sprintf(
            'Translation system contains %s paths, %s files, and %s translation keys across %s sources and %s locales.',
            $analytics['overview']['total_paths'],
            $analytics['overview']['total_files'],
            number_format($analytics['overview']['total_keys']),
            $analytics['overview']['total_sources'],
            $analytics['overview']['total_locales']
        ));
    }
}