<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Services;

use Illuminate\Support\Str;

/**
 * Service for handling naming conventions across the package.
 * Ensures consistent naming between type generation and translation export.
 */
class NamingService
{
    /**
     * Convert a name to filename-safe format (kebab-case).
     *
     * @param string $name
     * @return string
     */
    public function toFilenameSafe(string $name): string
    {
        return Str::kebab(strtolower($name));
    }

    /**
     * Build module/interface name from source and file.
     * Examples:
     * - ('vendors', 'actions') -> 'VendorsActions'
     * - ('vendors', 'bank-accounts.pages') -> 'VendorsBankAccountsPages'
     * - ('vendors', 'vendors.forms') -> 'VendorsFormsI18N' (avoids duplication)
     *
     * @param string $sourceName
     * @param string $file
     * @return string
     */
    public function buildModuleName(string $sourceName, string $file): string
    {
        // Convert file path dots to StudlyCase parts
        $parts = explode('.', $file);
        $moduleName = Str::studly($sourceName);
        
        foreach ($parts as $part) {
            // Skip the part if it matches the source name to avoid duplication
            // e.g., vendors/vendors/forms.php -> VendorsFormsI18N not VendorsVendorsFormsI18N
            if (strtolower($part) === strtolower($sourceName)) {
                continue;
            }
            $moduleName .= Str::studly($part);
        }
        
        return $moduleName;
    }

    /**
     * Get the export name for a translation module.
     *
     * @param string $moduleName
     * @param string $suffix
     * @return string
     */
    public function getExportName(string $moduleName, string $suffix = 'Translations'): string
    {
        return $moduleName . $suffix;
    }

    /**
     * Get the type name for a translation module.
     *
     * @param string $exportName
     * @return string
     */
    public function getTypeName(string $exportName): string
    {
        return $exportName . 'Type';
    }

    /**
     * Get the locales type name for a module.
     *
     * @param string $moduleName
     * @return string
     */
    public function getLocalesTypeName(string $moduleName): string
    {
        return $moduleName . 'Locales';
    }

    /**
     * Get the getter function name for a module.
     *
     * @param string $moduleName
     * @return string
     */
    public function getGetterFunctionName(string $moduleName): string
    {
        return 'get' . $moduleName;
    }

    /**
     * Determine the file name for a translation file in granular mode.
     *
     * @param string $file
     * @param bool $isJson
     * @return string
     */
    public function getGranularFileName(string $file, bool $isJson): string
    {
        if ($isJson) {
            return 'index';
        }
        
        // Replace dots with hyphens for nested files (bank-accounts.pages -> bank-accounts-pages)
        return Str::kebab(strtolower(str_replace('.', '-', $file)));
    }

    /**
     * Get the module file name for different modes.
     *
     * @param string $sourceName
     * @param string $mode
     * @return string
     */
    public function getModuleFileName(string $sourceName, string $mode): string
    {
        $baseName = $this->toFilenameSafe($sourceName);
        
        return match($mode) {
            'module' => $baseName . '.translations',
            'granular' => $baseName,
            default => $baseName
        };
    }

    /**
     * Extract source name from a module name.
     * Example: 'VendorsActions' -> 'vendors'
     *
     * @param string $moduleName
     * @param array<string> $knownSources
     * @return string|null
     */
    public function extractSourceName(string $moduleName, array $knownSources): ?string
    {
        foreach ($knownSources as $source) {
            $studlySource = Str::studly($source);
            if (str_starts_with($moduleName, $studlySource)) {
                return $source;
            }
        }
        
        return null;
    }

    /**
     * Get the file extension based on format.
     *
     * @param string $format
     * @return string
     */
    public function getFileExtension(string $format): string
    {
        return match ($format) {
            'typescript' => 'ts',
            'json' => 'json',
            'both' => 'ts',
            default => 'ts'
        };
    }

    /**
     * Convert a name to property name format (camelCase).
     *
     * @param string $name
     * @return string
     */
    public function toPropertyName(string $name): string
    {
        return Str::camel($name);
    }
}