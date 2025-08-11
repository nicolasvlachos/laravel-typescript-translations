<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Config;

use NVL\LaravelTypescriptTranslations\Enums\GenerationMode;
use NVL\LaravelTypescriptTranslations\Enums\OutputFormat;

/**
 * Configuration object for translation type generation.
 */
class TranslationConfig
{
    /**
     * Create a new configuration instance.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config
    ) {}

    /**
     * Get translation paths to scan.
     *
     * @return array<string>
     */
    public function getPaths(): array
    {
        $paths = $this->config['paths'] ?? ['lang', 'resources/lang'];
        return is_array($paths) ? $paths : [$paths];
    }

    /**
     * Get output configuration.
     *
     * @return OutputConfig
     */
    public function getOutput(): OutputConfig
    {
        return new OutputConfig(
            $this->config['output']['path'] ?? 'resources/js/types',
            $this->config['output']['filename'] ?? 'translations.d.ts'
        );
    }

    /**
     * Get interface suffix.
     *
     * @return string
     */
    public function getSuffix(): string
    {
        return $this->config['suffix'] ?? 'I18N';
    }

    /**
     * Get scan types.
     *
     * @return string
     */
    public function getScanTypes(): string
    {
        return $this->config['scan'] ?? 'all';
    }

    /**
     * Get base language for structure generation.
     *
     * @return string|null
     */
    public function getBaseLanguage(): ?string
    {
        return $this->config['base_language'] ?? null;
    }

    /**
     * Get excluded files.
     *
     * @return array<string>
     */
    public function getExcludedFiles(): array
    {
        $exclude = $this->config['exclude'] ?? [];
        return is_array($exclude) ? $exclude : [$exclude];
    }

    /**
     * Get generation mode.
     *
     * @return GenerationMode
     */
    public function getMode(): GenerationMode
    {
        $mode = $this->config['mode'] ?? 'single';
        return GenerationMode::from($mode);
    }

    /**
     * Get output format.
     *
     * @return OutputFormat
     */
    public function getFormat(): OutputFormat
    {
        $format = $this->config['format'] ?? 'nested';
        return OutputFormat::from($format);
    }

    /**
     * Check if vendor scanning is enabled.
     *
     * @return bool
     */
    public function shouldScanVendor(): bool
    {
        return $this->config['scan_vendor'] ?? false;
    }

    /**
     * Get vendor paths to scan.
     *
     * @return array<string>
     */
    public function getVendorPaths(): array
    {
        $paths = $this->config['vendor_paths'] ?? [];
        return is_array($paths) ? $paths : [$paths];
    }

    /**
     * Check if keys should be exported.
     *
     * @return bool
     */
    public function shouldExportKeys(): bool
    {
        return $this->config['export_keys'] ?? true;
    }

    /**
     * Get custom stubs path.
     *
     * @return string|null
     */
    public function getStubsPath(): ?string
    {
        return $this->config['stubs_path'] ?? null;
    }

    /**
     * Check if per-language types should be generated.
     *
     * @return bool
     */
    public function shouldGeneratePerLanguageTypes(): bool
    {
        return $this->config['per_language_types'] ?? false;
    }

    /**
     * Check if translations should be exported.
     *
     * @return bool
     */
    public function shouldExportTranslations(): bool
    {
        return $this->config['export_translations'] ?? false;
    }

    /**
     * Get translation naming configuration.
     *
     * @return array{prefix: string, suffix: string, locale_format: string}
     */
    public function getTranslationNaming(): array
    {
        return $this->config['translation_naming'] ?? [
            'prefix' => '',
            'suffix' => 'Translations',
            'locale_format' => 'snake',
        ];
    }

    /**
     * Get output organization configuration.
     *
     * @return array{enabled: bool, types_folder: string, enums_folder: string, translations_folder: string, keys_folder: string}
     */
    public function getOutputOrganization(): array
    {
        return $this->config['organize_output'] ?? [
            'enabled' => true,
            'types_folder' => 'types',
            'enums_folder' => 'enums',
            'translations_folder' => 'translations',
            'keys_folder' => 'keys',
        ];
    }

    /**
     * Get system translations name.
     *
     * @return string
     */
    public function getSystemTranslationsName(): string
    {
        return $this->config['system_translations_name'] ?? 'System';
    }

    /**
     * Override configuration values.
     *
     * @param array<string, mixed> $overrides
     * @return self
     */
    public function withOverrides(array $overrides): self
    {
        return new self(array_merge($this->config, $overrides));
    }
}