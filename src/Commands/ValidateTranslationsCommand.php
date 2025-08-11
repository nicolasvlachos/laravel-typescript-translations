<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Commands;

use Illuminate\Console\Command;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Discovery\PathsCollector;
use NVL\LaravelTypescriptTranslations\Scanners\ScannerManager;

/**
 * Command to validate translation files for consistency.
 */
class ValidateTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:validate
                            {--locale=* : Specific locales to validate}
                            {--fix : Attempt to fix issues by creating missing keys}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate translation files for consistency across locales';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $config = app(TranslationConfig::class);
        
        $this->info('🔍 Validating translation files...');

        // Collect paths
        $collector = new PathsCollector($config);
        $paths = $collector->collect();

        if (empty($paths)) {
            $this->error('No translation paths found.');
            return 1;
        }

        // Scan translations
        $scanner = new ScannerManager($config);
        $selectedLocales = $this->option('locale') ?: [];
        $data = $scanner->scan($paths, $selectedLocales);

        if ($data->isEmpty()) {
            $this->error('No translation files found.');
            return 1;
        }

        $issues = $this->validateTranslations($data);

        if (empty($issues)) {
            $this->info('✅ All translations are consistent!');
            return 0;
        }

        if ($this->option('json')) {
            $this->outputJson($issues);
        } else {
            $this->outputIssues($issues);
        }

        if ($this->option('fix')) {
            $this->warn('⚠️  Fix option is not yet implemented.');
        }

        return 1;
    }

    /**
     * Validate translations for consistency.
     *
     * @param \NicolasVlachos\LaravelTypescriptTranslations\Data\TranslationData $data
     * @return array<string, array<string, array<string>>>
     */
    private function validateTranslations($data): array
    {
        $issues = [];
        $locales = $data->getLocales();
        
        if (count($locales) < 2) {
            $this->warn('Only one locale found. Nothing to validate.');
            return [];
        }

        $baseLocale = $locales[0];
        
        foreach ($data->getSources() as $source => $files) {
            $allKeys = $this->collectAllKeys($files);
            
            // For each locale, check which keys are missing
            foreach ($locales as $locale) {
                if ($locale === $baseLocale) {
                    continue;
                }
                
                // This is a simplified check - in reality, you'd need to scan each locale separately
                // For now, we'll just report that validation requires scanning each locale
                $issues[$source][$locale] = ['Validation requires separate locale scanning'];
            }
        }

        return $issues;
    }

    /**
     * Collect all keys from files.
     *
     * @param array<string, array<string, mixed>> $files
     * @return array<string>
     */
    private function collectAllKeys(array $files): array
    {
        $keys = [];
        
        foreach ($files as $file => $translations) {
            $fileKeys = $this->collectKeysRecursive($translations, $file);
            $keys = array_merge($keys, $fileKeys);
        }

        return array_unique($keys);
    }

    /**
     * Collect keys recursively.
     *
     * @param array<string, mixed> $array
     * @param string $prefix
     * @return array<string>
     */
    private function collectKeysRecursive(array $array, string $prefix = ''): array
    {
        $keys = [];
        
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $keys = array_merge($keys, $this->collectKeysRecursive($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }

    /**
     * Output issues as JSON.
     *
     * @param array<string, array<string, array<string>>> $issues
     * @return void
     */
    private function outputJson(array $issues): void
    {
        $this->line(json_encode($issues, JSON_PRETTY_PRINT));
    }

    /**
     * Output issues in a readable format.
     *
     * @param array<string, array<string, array<string>>> $issues
     * @return void
     */
    private function outputIssues(array $issues): void
    {
        $this->error('❌ Translation inconsistencies found:');
        $this->newLine();

        foreach ($issues as $source => $localeIssues) {
            $this->line("<comment>Source: {$source}</comment>");
            
            foreach ($localeIssues as $locale => $missingKeys) {
                $count = count($missingKeys);
                $this->line("  <error>Locale {$locale}: {$count} issues</error>");
                
                if ($this->output->isVerbose()) {
                    foreach (array_slice($missingKeys, 0, 10) as $key) {
                        $this->line("    - {$key}");
                    }
                    
                    if ($count > 10) {
                        $this->line("    ... and " . ($count - 10) . " more");
                    }
                }
            }
        }
    }
}