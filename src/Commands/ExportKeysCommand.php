<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Discovery\PathsCollector;
use NVL\LaravelTypescriptTranslations\Generators\TypeScriptGenerator;
use NVL\LaravelTypescriptTranslations\Scanners\ScannerManager;

/**
 * Command to export translation keys as TypeScript types.
 */
class ExportKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:export-keys
                            {--output= : Output file path}
                            {--format=union : Format: union, enum, or const}
                            {--source=* : Specific sources to export}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export translation keys as TypeScript types for type-safe key usage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $config = app(TranslationConfig::class);
        
        $this->info('🔍 Scanning for translation keys...');

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

        $selectedSources = $this->option('source') ?: [];
        $format = $this->option('format');
        $outputPath = $this->getOutputPath($config);

        $content = $this->generateKeysExport($data, $selectedSources, $format, $config);
        
        $this->writeOutput($outputPath, $content);
        
        $this->info("✅ Translation keys exported to: {$outputPath}");

        return 0;
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

        $outputConfig = $config->getOutput();
        $basePath = base_path($outputConfig->getPath());
        $organization = $config->getOutputOrganization();
        $format = $this->option('format');
        
        if ($organization['enabled']) {
            $folder = match ($format) {
                'enum' => $organization['enums_folder'],
                'const' => $organization['keys_folder'],
                default => $organization['types_folder'],
            };
            
            return $basePath . '/' . $folder . '/translation-keys.d.ts';
        }
        
        return $basePath . '/translation-keys.d.ts';
    }

    /**
     * Generate keys export content.
     *
     * @param \NicolasVlachos\LaravelTypescriptTranslations\Data\TranslationData $data
     * @param array<string> $selectedSources
     * @param string $format
     * @param TranslationConfig $config
     * @return string
     */
    private function generateKeysExport($data, array $selectedSources, string $format, TranslationConfig $config): string
    {
        $generator = new TypeScriptGenerator($config);
        $ts = $generator->generateHeader('Translation Keys Export');

        $sources = $data->getSources();
        
        if (!empty($selectedSources)) {
            $sources = array_filter(
                $sources,
                fn($key) => in_array($key, $selectedSources),
                ARRAY_FILTER_USE_KEY
            );
        }

        foreach ($sources as $sourceName => $structure) {
            $keys = $generator->generateKeys($structure);
            
            if (empty($keys)) {
                continue;
            }

            $ts .= "// Keys for {$sourceName}\n";
            
            switch ($format) {
                case 'enum':
                    $ts .= $this->generateEnum($sourceName, $keys);
                    break;
                    
                case 'const':
                    $ts .= $this->generateConst($sourceName, $keys);
                    break;
                    
                default: // union
                    $ts .= $generator->generateKeysType($sourceName . 'Key', $keys);
                    break;
            }
            
            $ts .= "\n";
        }

        // Generate combined type
        if (count($sources) > 1) {
            $ts .= "// Combined translation keys\n";
            
            switch ($format) {
                case 'enum':
                    $allKeys = [];
                    foreach ($sources as $sourceName => $structure) {
                        $keys = $generator->generateKeys($structure);
                        foreach ($keys as $key) {
                            $allKeys[] = $sourceName . '.' . $key;
                        }
                    }
                    $ts .= $this->generateEnum('TranslationKey', $allKeys);
                    break;
                    
                case 'const':
                    $allKeys = [];
                    foreach ($sources as $sourceName => $structure) {
                        $keys = $generator->generateKeys($structure);
                        foreach ($keys as $key) {
                            $allKeys[$sourceName . '.' . $key] = $sourceName . '.' . $key;
                        }
                    }
                    $ts .= $this->generateConst('TranslationKeys', $allKeys);
                    break;
                    
                default: // union
                    $unionTypes = [];
                    foreach (array_keys($sources) as $sourceName) {
                        $unionTypes[] = $sourceName . 'Key';
                    }
                    $ts .= "export type TranslationKey = " . implode(' | ', $unionTypes) . ";\n";
                    break;
            }
        }

        return $ts;
    }

    /**
     * Generate enum format.
     *
     * @param string $name
     * @param array<string> $keys
     * @return string
     */
    private function generateEnum(string $name, array $keys): string
    {
        $ts = "export enum {$name} {\n";
        
        foreach ($keys as $key) {
            $enumKey = str_replace(['.', '-', ' '], '_', strtoupper($key));
            $ts .= "  {$enumKey} = '{$key}',\n";
        }
        
        $ts .= "}\n";
        
        return $ts;
    }

    /**
     * Generate const format.
     *
     * @param string $name
     * @param array<string, string> $keys
     * @return string
     */
    private function generateConst(string $name, array $keys): string
    {
        $ts = "export const {$name} = {\n";
        
        foreach ($keys as $key => $value) {
            $constKey = str_replace(['.', '-', ' '], '_', is_numeric($key) ? $value : $key);
            $ts .= "  {$constKey}: '{$value}',\n";
        }
        
        $ts .= "} as const;\n";
        $ts .= "\n";
        $ts .= "export type {$name}Type = typeof {$name}[keyof typeof {$name}];\n";
        
        return $ts;
    }

    /**
     * Write output to file.
     *
     * @param string $path
     * @param string $content
     * @return void
     */
    private function writeOutput(string $path, string $content): void
    {
        $directory = dirname($path);
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, $content);
    }
}