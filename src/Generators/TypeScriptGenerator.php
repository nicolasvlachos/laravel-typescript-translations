<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Generators;

use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;
use NVL\LaravelTypescriptTranslations\Enums\OutputFormat;

/**
 * Generates TypeScript code from translation data.
 */
class TypeScriptGenerator
{
    /**
     * Create a new generator instance.
     *
     * @param TranslationConfig $config
     */
    public function __construct(
        private readonly TranslationConfig $config
    ) {}

    /**
     * Generate TypeScript interface structure.
     *
     * @param array<string, mixed> $data
     * @param int $indent
     * @return string
     */
    public function generateStructure(array $data, int $indent = 2): string
    {
        if ($this->config->getFormat() === OutputFormat::FLAT) {
            return $this->generateFlatStructure($data, $indent);
        }
        
        return $this->generateNestedStructure($data, $indent);
    }

    /**
     * Generate nested TypeScript structure.
     *
     * @param array<string, mixed> $data
     * @param int $indent
     * @return string
     */
    public function generateNestedStructure(array $data, int $indent): string
    {
        $ts = '';
        $spaces = str_repeat(' ', $indent);
        
        foreach ($data as $key => $value) {
            $safeKey = $this->getSafeKey((string) $key);
            
            if (is_array($value)) {
                if ($this->isSequentialArray($value)) {
                    $ts .= "{$spaces}{$safeKey}: string[];\n";
                } else {
                    $ts .= "{$spaces}{$safeKey}: {\n";
                    $ts .= $this->generateNestedStructure($value, $indent + 2);
                    $ts .= "{$spaces}};\n";
                }
            } else {
                $ts .= "{$spaces}{$safeKey}: string;\n";
            }
        }
        
        return $ts;
    }

    /**
     * Generate flat TypeScript structure.
     *
     * @param array<string, mixed> $data
     * @param int $indent
     * @return string
     */
    public function generateFlatStructure(array $data, int $indent): string
    {
        $flattened = $this->flattenArray($data);
        $ts = '';
        $spaces = str_repeat(' ', $indent);
        
        foreach (array_keys($flattened) as $key) {
            $ts .= "{$spaces}'{$key}': string;\n";
        }
        
        return $ts;
    }

    /**
     * Generate keys type for a structure.
     *
     * @param array<string, mixed> $structure
     * @param string $prefix
     * @return array<string>
     */
    public function generateKeys(array $structure, string $prefix = ''): array
    {
        $keys = [];
        
        foreach ($structure as $key => $value) {
            if ($key === '_json' && $prefix === '') {
                if (is_array($value)) {
                    $keys = array_merge($keys, $this->generateKeys($value, ''));
                }
                continue;
            }
            
            $currentKey = $prefix === '' ? (string) $key : "{$prefix}." . (string) $key;
            
            if (is_array($value)) {
                if ($this->isSequentialArray($value)) {
                    $keys[] = $currentKey;
                } else {
                    $nestedKeys = $this->generateKeys($value, $currentKey);
                    $keys = array_merge($keys, $nestedKeys);
                }
            } else {
                $keys[] = $currentKey;
            }
        }
        
        return $keys;
    }

    /**
     * Get safe TypeScript key.
     *
     * @param string $key
     * @return string
     */
    public function getSafeKey(string $key): string
    {
        // Replace hyphens, dots, spaces, and slashes with underscores for valid TypeScript identifiers
        $safeKey = str_replace(['-', '.', ' ', '/', '\\'], '_', $key);
        
        // Check if key starts with a number or still needs quotes
        $needsQuotes = (isset($safeKey[0]) && is_numeric($safeKey[0]));
            
        return $needsQuotes ? "'{$safeKey}'" : $safeKey;
    }

    /**
     * Flatten a multidimensional array.
     *
     * @param array<string, mixed> $array
     * @param string $prefix
     * @param string $separator
     * @return array<string, mixed>
     */
    public function flattenArray(array $array, string $prefix = '', string $separator = '.'): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $newKey = $prefix === '' ? "{$key}{$separator}{$subKey}" : "{$prefix}{$separator}{$key}{$separator}{$subKey}";
                    
                    if (is_array($subValue)) {
                        $result = array_merge($result, $this->flattenArray([$subKey => $subValue], $newKey, $separator));
                    } else {
                        $result[$newKey] = $subValue;
                    }
                }
            } else {
                $newKey = $prefix === '' ? $key : "{$prefix}{$separator}{$key}";
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Check if array is sequential.
     *
     * @param array<mixed> $array
     * @return bool
     */
    private function isSequentialArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Generate file header.
     *
     * @param string|null $description
     * @return string
     */
    public function generateHeader(?string $description = null): string
    {
        $header = "// Auto-generated TypeScript types from Laravel translation files\n";
        $header .= "// Generated at: " . now()->toIso8601String() . "\n";
        
        if ($description) {
            $header .= "// {$description}\n";
        }
        
        $header .= "\n";
        
        return $header;
    }

    /**
     * Generate keys type definition.
     *
     * @param array<string> $keys
     * @param string $typeName
     * @return string
     */
    public function generateKeysType(array $keys, string $typeName): string
    {
        if (empty($keys)) {
            return "export type {$typeName} = never;\n";
        }
        
        $quotedKeys = array_map(fn($k) => "'{$k}'", $keys);
        return "export type {$typeName} = " . implode(' | ', $quotedKeys) . ";\n";
    }
}