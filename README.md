# Laravel TypeScript Translations

Generate TypeScript type definitions from Laravel translation files for type-safe internationalization.

## Features

- **Type-safe translations** - Full TypeScript support for Laravel translations
- **Multiple generation modes** - Single file, module-based, or granular output
- **Smart discovery** - Automatically finds translation files in your project
- **Vendor support** - Scan translations from vendor packages
- **Export translation keys** - Generate type-safe keys for translation functions
- **Translation objects export** - Export actual translation values as JS/TS modules
- **Flexible configuration** - Extensive customization options

## Requirements

- PHP 8.2 or higher
- Laravel 12.0 or higher

## Installation

Install via Composer:

```bash
composer require nicolasvlachos/laravel-typescript-translations --dev
```

## Quick Start

Generate TypeScript types with a single command:

```bash
php artisan translations:generate
```

This scans your translation files and generates TypeScript definitions in `resources/js/types/translations.d.ts`.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=typescript-translations-config
```

Key configuration options in `config/typescript-translations.php`:

```php
return [
    'paths' => [
        'lang',
        'resources/lang',
        'Modules/*', // Wildcard support
    ],
    
    'output' => [
        'path' => 'resources/js/types',
        'filename' => 'translations.d.ts',
    ],
    
    'mode' => 'single', // single, module, or granular
    'format' => 'nested', // nested or flat
    'base_language' => 'en',
    'system_translations_name' => 'System',
];
```

## Commands

### Generate Types

```bash
php artisan translations:generate [options]
```

Options:
- `--mode=<mode>` - Generation mode: single, module, or granular
- `--format=<format>` - Output format: nested or flat
- `--locale=<locale>` - Specific locales to process (repeatable)
- `--output=<path>` - Custom output path
- `--scan-vendor` - Include vendor translations

### Export Translation Objects

Export actual translation values as JavaScript/TypeScript modules:

```bash
php artisan translations:export [options]
```

Options:
- `--format=<format>` - Output format: js or ts
- `--module=<type>` - Module format: esm or commonjs
- `--per-locale` - Export separate files per locale

### Export Translation Keys

Generate type-safe translation keys:

```bash
php artisan translations:export-keys [options]
```

Options:
- `--format=<format>` - Format: union, enum, or const
- `--source=<source>` - Specific sources to export (repeatable)

### Analytics

Display detailed translation statistics:

```bash
php artisan translations:analytics [options]
```

Options:
- `--detailed` - Show detailed breakdown
- `--json` - Output as JSON

### Scan & Validate

```bash
# Scan translation files
php artisan translations:scan [--json] [--verbose]

# Validate consistency across locales
php artisan translations:validate [--locale=<locale>] [--json]
```

## Generation Modes

### Single File Mode

All types in one file:

```typescript
export interface SystemI18N {
  auth: {
    login: string;
    logout: string;
  };
}

export interface I18N {
  system: SystemI18N;
}
```

### Module Mode

Separate files per translation source:

```
resources/js/types/
├── translations.d.ts
└── translations/
    ├── shared.types.d.ts
    ├── system.translations.d.ts
    └── app.translations.d.ts
```

### Granular Mode

Individual files for each translation file:

```
resources/js/types/
└── translations/
    ├── index.d.ts
    ├── shared.types.d.ts
    └── system/
        ├── index.d.ts
        └── auth.translations.d.ts
```

## Usage in TypeScript

```typescript
import type { I18N, Locale, TranslationKey } from '@/types/translations';

// Type-safe translation function
function t(key: TranslationKey, params?: Record<string, any>): string {
  // Implementation
}

// Type-safe locale switching
function setLocale(locale: Locale): void {
  // Implementation
}

// Strongly typed translations
const translations: I18N = {
  system: {
    auth: {
      login: 'Login',
      logout: 'Logout',
    },
  },
};
```

## Advanced Features

### Per-Language Types

Generate separate types for each language when structures differ:

```php
'per_language_types' => true,
```

### Organized Output

Organize generated files into subdirectories:

```php
'organize_output' => [
    'enabled' => true,
    'types_folder' => 'types',
    'enums_folder' => 'enums',
    'translations_folder' => 'translations',
],
```

### Wildcard Path Discovery

Automatically discover translation paths:

```php
'paths' => [
    'Modules/*',           // All module directories
    'packages/*/lang',     // All package lang directories
],
```

### Custom Stubs

Customize generation templates:

```bash
php artisan vendor:publish --tag=typescript-translations-stubs
```

## Publishing Assets

```bash
# Publish everything
php artisan vendor:publish --tag=typescript-translations

# Publish only config
php artisan vendor:publish --tag=typescript-translations-config

# Publish only stubs
php artisan vendor:publish --tag=typescript-translations-stubs
```

## Architecture

The package follows a modular architecture:

| Component | Description |
|-----------|-------------|
| **Discovery** | `PathsCollector` finds translation directories with wildcard support |
| **Scanning** | Pluggable scanners for different file types (PHP, JSON) |
| **Generation** | `TypeScriptGenerator` creates TypeScript code |
| **Writing** | Multiple writer strategies for different output modes |
| **Configuration** | Type-safe configuration with value objects and enums |

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Security

If you discover any security related issues, please email vlachos.ni@gmail.com instead of using the issue tracker.

## Credits

- [Nicolas Vlachos](https://github.com/nicolasvlachos)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.