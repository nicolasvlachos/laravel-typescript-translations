<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Translation Paths
    |--------------------------------------------------------------------------
    |
    | Paths to scan for translation files. These can be relative to the
    | Laravel base path or absolute paths. Each path will be scanned for
    | language directories and files.
    |
    */
    'paths' => [
        'lang',
        'resources/lang',
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where and how the TypeScript type definitions should be
    | generated. The path is relative to the Laravel base path.
    |
    */
    'output' => [
        'path' => 'resources/js/types',
        'filename' => 'translations.d.ts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Interface Suffix
    |--------------------------------------------------------------------------
    |
    | The suffix to append to generated TypeScript interface names.
    | For example, with suffix 'I18N', a 'System' module becomes 'SystemI18N'.
    |
    */
    'suffix' => 'I18N',

    /*
    |--------------------------------------------------------------------------
    | File Types to Scan
    |--------------------------------------------------------------------------
    |
    | Specify which file types to scan for translations.
    | Options: 'json', 'php', 'all'
    |
    */
    'scan' => 'all',

    /*
    |--------------------------------------------------------------------------
    | Base Language
    |--------------------------------------------------------------------------
    |
    | The base language to use for generating TypeScript structure.
    | When set, only this language will be scanned for structure definition.
    | This is useful when all languages have the same translation keys.
    | Set to null to scan all languages.
    |
    */
    'base_language' => null,

    /*
    |--------------------------------------------------------------------------
    | Excluded Files
    |--------------------------------------------------------------------------
    |
    | Files to exclude from scanning. You can specify file names with or
    | without the .php extension. These files will be ignored during
    | the translation scanning process.
    |
    */
    'exclude' => [
        // 'validation',
        // 'passwords',
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation Mode
    |--------------------------------------------------------------------------
    |
    | How to organize the generated TypeScript files:
    | - 'single': All types in one file
    | - 'module': Separate files per module/source
    | - 'granular': Separate files for each translation file
    |
    */
    'mode' => 'single',

    /*
    |--------------------------------------------------------------------------
    | Output Format
    |--------------------------------------------------------------------------
    |
    | The structure format for the generated types:
    | - 'nested': Maintains hierarchical structure
    | - 'flat': All keys as dot notation
    |
    */
    'format' => 'nested',

    /*
    |--------------------------------------------------------------------------
    | Scan Vendor Translations
    |--------------------------------------------------------------------------
    |
    | Enable scanning of vendor translation files that haven't been published.
    | This will scan the vendor directory for package translations.
    |
    */
    'scan_vendor' => false,

    /*
    |--------------------------------------------------------------------------
    | Vendor Paths
    |--------------------------------------------------------------------------
    |
    | Additional vendor paths to scan for translations when scan_vendor is true.
    |
    */
    'vendor_paths' => [
        // 'vendor/package-name/resources/lang',
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Keys
    |--------------------------------------------------------------------------
    |
    | Generate TypeScript types for translation keys as string literals.
    | This enables better type safety when using translation keys.
    |
    */
    'export_keys' => true,

    /*
    |--------------------------------------------------------------------------
    | Custom Stubs Path
    |--------------------------------------------------------------------------
    |
    | Path to custom stub templates. If set, the package will use your
    | custom templates instead of the default ones.
    |
    */
    'stubs_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Per-Language Types
    |--------------------------------------------------------------------------
    |
    | Generate separate types for each language when their structures differ.
    | This is useful when translations have different keys across languages.
    |
    */
    'per_language_types' => false,

    /*
    |--------------------------------------------------------------------------
    | Export Translation Objects
    |--------------------------------------------------------------------------
    |
    | Export actual translation values as JavaScript/TypeScript objects.
    | Warning: This will include all translation strings in your bundle.
    |
    */
    'export_translations' => false,

    /*
    |--------------------------------------------------------------------------
    | Translation Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how and where translation data is exported.
    |
    */
    'translation_export' => [
        'path' => 'resources/js/data/translations',
        'mode' => 'module', // 'single', 'module', 'granular' - matches type generation mode
        'organize_by' => 'locale-mapped', // 'locale', 'module', 'locale-mapped' - primary organization structure
        'format' => 'typescript', // 'typescript', 'json', 'both'
        'filename_pattern' => '{locale}.ts', // Pattern for filenames: {locale}, {module}, {file}
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Object Naming
    |--------------------------------------------------------------------------
    |
    | Configure how exported translation objects are named.
    |
    */
    'translation_naming' => [
        'prefix' => '',
        'suffix' => 'Translations',
        'locale_format' => 'snake', // snake, kebab, camel, studly
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Organization
    |--------------------------------------------------------------------------
    |
    | Organize generated files into subdirectories.
    |
    */
    'organize_output' => [
        'enabled' => true,
        'types_folder' => 'types',
        'enums_folder' => 'enums',
        'translations_folder' => 'translations',
        'keys_folder' => 'keys',
    ],

    /*
    |--------------------------------------------------------------------------
    | System Translations Name
    |--------------------------------------------------------------------------
    |
    | Configure the name for system translations (main lang folder).
    |
    */
    'system_translations_name' => 'System',
];