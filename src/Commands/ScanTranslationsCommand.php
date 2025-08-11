<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Commands;

use Illuminate\Console\Command;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Discovery\PathsCollector;
use NVL\LaravelTypescriptTranslations\Scanners\ScannerManager;

/**
 * Command to scan and display translation files information.
 */
class ScanTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:scan
                            {--json : Output as JSON}
                            {--verbose : Show detailed information}
                            {--scan-vendor : Include vendor translations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan and display information about translation files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $config = $this->getConfiguration();
        
        $this->info('🔍 Scanning for translation files...');

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

        if ($this->option('json')) {
            $this->outputJson($data);
        } else {
            $this->outputTable($data);
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
     * Output data as JSON.
     *
     * @param \NicolasVlachos\LaravelTypescriptTranslations\Data\TranslationData $data
     * @return void
     */
    private function outputJson($data): void
    {
        $output = [
            'locales' => $data->getLocales(),
            'sources' => [],
        ];

        foreach ($data->getSources() as $source => $files) {
            $output['sources'][$source] = [
                'files' => array_keys($files),
                'keys_count' => $this->countKeys($files),
            ];
        }

        $this->line(json_encode($output, JSON_PRETTY_PRINT));
    }

    /**
     * Output data as table.
     *
     * @param \NicolasVlachos\LaravelTypescriptTranslations\Data\TranslationData $data
     * @return void
     */
    private function outputTable($data): void
    {
        $this->info('📊 Translation Statistics:');
        $this->newLine();

        $this->line('<comment>Locales:</comment> ' . implode(', ', $data->getLocales()));
        $this->newLine();

        $tableData = [];
        foreach ($data->getSources() as $source => $files) {
            $fileCount = count($files);
            $keyCount = $this->countKeys($files);
            
            $tableData[] = [$source, $fileCount, $keyCount];

            if ($this->option('verbose')) {
                foreach ($files as $file => $translations) {
                    $fileKeyCount = $this->countKeys(['file' => $translations]);
                    $tableData[] = ['  └─ ' . $file, '', $fileKeyCount];
                }
            }
        }

        $this->table(
            ['Source', 'Files', 'Total Keys'],
            $tableData
        );

        $totalSources = count($data->getSources());
        $totalKeys = array_sum(array_map(
            fn($files) => $this->countKeys($files),
            $data->getSources()
        ));

        $this->newLine();
        $this->info("📈 Total: {$totalSources} sources, {$totalKeys} translation keys");
    }

    /**
     * Count keys in translation files.
     *
     * @param array<string, array<string, mixed>> $files
     * @return int
     */
    private function countKeys(array $files): int
    {
        $count = 0;
        
        foreach ($files as $translations) {
            $count += $this->countKeysRecursive($translations);
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
}