# Laravel TypeScript Translations

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nvl/laravel-typescript-translations.svg?style=flat-square)](https://packagist.org/packages/nvl/laravel-typescript-translations)
[![Total Downloads](https://img.shields.io/packagist/dt/nvl/laravel-typescript-translations.svg?style=flat-square)](https://packagist.org/packages/nvl/laravel-typescript-translations)

Generate TypeScript types from your Laravel translation files.

## What it does

This package scans your Laravel translation files and generates corresponding TypeScript type definitions. Instead of one giant type file, it creates composable, modular types that mirror your translation structure. This means you can import only what you need, mix translations from different modules, and keep your types organized.

## Installation

```bash
composer require nvl/laravel-typescript-translations --dev
```

## Basic Usage

```bash
php artisan translations:generate
```

That's it. Your TypeScript types are now generated in `resources/js/types/translations.d.ts`.

## Why composable types matter

Instead of getting one massive type with everything, you get this:

```typescript
// Each module has its own types
export interface VendorsActionsI18N {
    create: string;
    edit: string;
    delete: string;
}

export interface VendorsFormsI18N {
    name: string;
    email: string;
    phone: string;
}

// Compose them as needed
export interface VendorsI18N {
    actions: VendorsActionsI18N;
    forms: VendorsFormsI18N;
}
```

Now you can use exactly what you need:

```typescript
// Just need vendor actions? Import only that
import type { VendorsActionsI18N } from '@/types/translations';

// Need to compose your own type for a specific page?
type VendorEditPage = {
    vendors: {
        actions: Pick<VendorsActionsI18N, 'save' | 'cancel'>;
        forms: VendorsFormsI18N;
    };
    common: CommonI18N;
}
```

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag="typescript-translations-config"
```

```php
return [
    // Where to look for translation files
    'paths' => [
        'lang',
        'resources/lang',
        'Modules/*/Resources/lang',  // Scan module translations
        'packages/*/lang',            // Scan package translations
    ],
    
    // Where to put generated types
    'output' => [
        'path' => 'resources/js/types',
        'filename' => 'translations.d.ts',
    ],
    
    // How to generate types
    'mode' => 'module',    // 'single', 'module', or 'granular'
    'format' => 'nested',  // 'nested' or 'flat'
];
```

## Generation Modes

### Module Mode (Recommended)
Creates a separate file for each translation source. Best for projects with multiple modules.

```
resources/js/types/
├── translations.d.ts
└── translations/
    ├── vendors.translations.d.ts
    ├── products.translations.d.ts
    └── system.translations.d.ts
```

### Single Mode
Everything in one file. Good for small projects.

### Granular Mode
One file per translation file. Maximum flexibility.

```
resources/js/types/translations/
├── vendors/
│   ├── actions.translations.d.ts
│   ├── forms.translations.d.ts
│   └── tables.translations.d.ts
└── products/
    ├── catalog.translations.d.ts
    └── inventory.translations.d.ts
```

## Using with Inertia

Works great with Laravel Inertia. Share common translations in your middleware, then add page-specific ones in your controllers:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        // Share only common translations globally
        'translations' => [
            'common' => trans('common'),
            'navigation' => trans('navigation'),
        ],
    ]);
}
```

```php
// In your controller - add page-specific translations
return Inertia::render('Vendors/Show', [
    'vendor' => $vendor,
    'translations' => [
        'vendors' => trans('vendors::vendors'),
        'products' => [
            'filters' => trans('products::filters')
        ]
    ]
]);
```

Then use them with full type safety:

```vue
<script setup lang="ts">
import type { CommonI18N, VendorsI18N, ProductsFiltersI18N } from '@/types/translations';
import { usePage } from '@inertiajs/vue3';

// Global translations from middleware
const { common, navigation } = usePage().props.translations;

// Page-specific translations from controller
const props = defineProps<{
    vendor: Vendor;
    translations: {
        vendors: VendorsI18N;
        products: {
            filters: ProductsFiltersI18N;
        };
    };
}>();

// Use both global and page-specific translations
const saveLabel = common.save;
const vendorName = props.translations.vendors.name;
const filterLabel = props.translations.products.filters.category;
</script>
```

## Using with use-inertia-translations

If you're using the [use-inertia-translations](https://github.com/your-username/use-inertia-translations) package:

```typescript
import { useInertiaTranslations } from 'use-inertia-translations';
import type { I18N, TranslationKey } from '@/types/translations';

const { t } = useInertiaTranslations<I18N>();

// Full type safety for translation keys
const label = t('vendors.actions.create');
```

## Commands

### Generate Types
```bash
php artisan translations:generate

# Options
--mode=module       # Generation mode
--format=nested     # Output format
--locale=en,es      # Specific locales
--scan-vendor       # Include vendor translations
```

### Export Translation Keys
```bash
php artisan translations:export-keys

# Generates a union type of all translation keys
export type TranslationKey = 
    | 'vendors.actions.create'
    | 'vendors.actions.edit'
    | 'products.catalog.title'
    // ...
```

### Export Translation Values
```bash
php artisan translations:export

# Exports actual translation values as JS/TS
export const translations = {
    vendors: {
        actions: {
            create: 'Create Vendor',
            edit: 'Edit Vendor',
        }
    }
}
```

### Scan & Validate
```bash
# See what translations you have
php artisan translations:scan

# Check for missing keys across locales
php artisan translations:validate

# Get detailed stats
php artisan translations:analytics
```

## Real-world Example

Say you have this structure:

```
lang/en/
├── vendors/
│   ├── actions.php
│   ├── forms.php
│   └── tables.php
├── products/
│   ├── catalog.php
│   └── inventory.php
└── common.php
```

This package generates types that match this structure exactly. You can then:

1. Import entire modules when you need everything
2. Pick specific parts for optimized page loads
3. Compose custom types for specific components
4. Mix translations from different modules freely

```typescript
// Use everything from vendors
import type { VendorsI18N } from '@/types/translations';

// Or compose your own type
type MyComponentTranslations = {
    vendorActions: VendorsActionsI18N;
    productCatalog: ProductsCatalogI18N;
    common: Pick<CommonI18N, 'save' | 'cancel'>;
}
```

## Advanced Features

### Per-Language Types

If your languages have different keys:

```php
'per_language_types' => true,
```

### Exclude Patterns

Skip certain files:

```php
'exclude' => [
    '*/test/*',
    'temp-*',
],
```

### Custom Output Organization

```php
'organize_output' => [
    'enabled' => true,
    'types_folder' => 'types',
    'translations_folder' => 'translations',
],
```

## Testing

```bash
composer test
```

## License

MIT. See [LICENSE.md](LICENSE.md) for details.