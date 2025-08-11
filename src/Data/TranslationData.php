<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Data;

/**
 * Data transfer object for translation data.
 */
class TranslationData
{
    /**
     * Translation sources and their structures.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $sources = [];

    /**
     * Available locales.
     *
     * @var array<string>
     */
    private array $locales = [];

    /**
     * Add translations for a source and file.
     *
     * @param string $source
     * @param string $fileKey
     * @param array<string, mixed> $translations
     * @return void
     */
    public function addTranslations(string $source, string $fileKey, array $translations): void
    {
        if (!isset($this->sources[$source])) {
            $this->sources[$source] = [];
        }

        if (!isset($this->sources[$source][$fileKey])) {
            $this->sources[$source][$fileKey] = [];
        }

        $this->sources[$source][$fileKey] = array_merge(
            $this->sources[$source][$fileKey],
            $translations
        );
    }

    /**
     * Add a locale.
     *
     * @param string $locale
     * @return void
     */
    public function addLocale(string $locale): void
    {
        if (!in_array($locale, $this->locales)) {
            $this->locales[] = $locale;
        }
    }

    /**
     * Get all sources.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Get translations for a specific source.
     *
     * @param string $source
     * @return array<string, array<string, mixed>>
     */
    public function getSource(string $source): array
    {
        return $this->sources[$source] ?? [];
    }

    /**
     * Get all locales.
     *
     * @return array<string>
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * Check if data is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->sources);
    }

    /**
     * Check if a source exists.
     *
     * @param string $source
     * @return bool
     */
    public function hasSource(string $source): bool
    {
        return isset($this->sources[$source]);
    }

    /**
     * Get source names.
     *
     * @return array<string>
     */
    public function getSourceNames(): array
    {
        return array_keys($this->sources);
    }

    /**
     * Merge with another TranslationData instance.
     *
     * @param TranslationData $other
     * @return void
     */
    public function merge(TranslationData $other): void
    {
        foreach ($other->getSources() as $source => $files) {
            foreach ($files as $fileKey => $translations) {
                $this->addTranslations($source, $fileKey, $translations);
            }
        }

        foreach ($other->getLocales() as $locale) {
            $this->addLocale($locale);
        }
    }
}