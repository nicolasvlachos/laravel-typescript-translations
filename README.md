# Laravel TypeScript Translations

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nvl/laravel-typescript-translations.svg?style=flat-square)](https://packagist.org/packages/nvl/laravel-typescript-translations)
[![Total Downloads](https://img.shields.io/packagist/dt/nvl/laravel-typescript-translations.svg?style=flat-square)](https://packagist.org/packages/nvl/laravel-typescript-translations)

Generate TypeScript types and translation data from Laravel translation files. **Load only what you need, when you need it.**

## Table of Contents

- [The Problem This Solves](#the-problem-this-solves)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Understanding the Two Approaches](#understanding-the-two-approaches)
- [Mode Comparison](#mode-comparison)
- [Type Generation](#type-generation)
- [Translation Export](#translation-export)
- [Hook Usage](#hook-usage)
  - [use-inertia-translations (Backend)](#use-inertia-translations-backend)
  - [use-local-translations (Frontend)](#use-local-translations-frontend)
- [Laravel Integration](#laravel-integration)
- [Configuration Options](#configuration-options)
- [Performance](#performance)
- [License](#license)

## The Problem This Solves

In Laravel + TypeScript apps, you typically have:
1. **Massive translation bundles** - Loading all translations for all languages on every page
2. **No type safety** - Typos in translation keys only discovered at runtime
3. **Poor performance** - Sending unused translations over the wire

This package solves all three by:
- Generating TypeScript types from your Laravel translations
- Exporting translation data in a modular, tree-shakeable format
- Providing hooks for both backend-driven and frontend-driven translations
- Enabling fine-grained control over what translations load where

## Installation

```bash
composer require nvl/laravel-typescript-translations --dev
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=typescript-translations-config
```

## Quick Start

```bash
# 1. Generate TypeScript types for backend translations
php artisan translations:generate --mode=module

# 2. Export translation data for frontend usage
php artisan translations:export --mode=module --organize-by=locale-mapped
```

## Understanding the Two Approaches

This package supports two distinct approaches for handling translations:

### 1. Backend-Driven (use-inertia-translations)
- Server sends specific translations for current page
- Only current locale
- Smaller payload
- No client-side locale switching
- Type-safe with generated interfaces

### 2. Frontend-Driven (use-local-translations)
- Client loads translation modules
- All locales available
- Larger initial payload
- Dynamic locale switching
- Tree-shakeable modules

## Mode Comparison

### Type Generation Modes

| Mode | Output Structure | File Count | Use Case |
|------|-----------------|------------|----------|
| **Single** | `translations.d.ts` | 1 file | Small projects (< 10 translation files) |
| **Module** | `vendors.types.d.ts`, `tasks.types.d.ts` | 1 per source | **Recommended** - Balanced approach |
| **Granular** | `vendors/actions.types.d.ts`, `vendors/forms.types.d.ts` | 1 per file | Large projects needing maximum control |

#### Single Mode Structure
```typescript
// resources/js/types/translations.d.ts
export interface I18N {
  vendors: {
    actions: VendorsActionsI18N;
    forms: VendorsFormsI18N;
  };
  tasks: {
    actions: TasksActionsI18N;
    filters: TasksFiltersI18N;
  };
}
```

#### Module Mode Structure
```typescript
// resources/js/types/translations/vendors.types.d.ts
export interface VendorsActionsI18N { create: string; edit: string; }
export interface VendorsFormsI18N { title: string; fields: {...} }
export interface VendorsI18N { 
  actions: VendorsActionsI18N;
  forms: VendorsFormsI18N;
}
```

#### Granular Mode Structure
```
resources/js/types/translations/
├── vendors/
│   ├── actions.types.d.ts    // Just VendorsActionsI18N
│   ├── forms.types.d.ts      // Just VendorsFormsI18N
│   └── index.d.ts
└── tasks/
    ├── actions.types.d.ts
    └── filters.types.d.ts
```

### Translation Export Modes

| Mode | Output Structure | Bundle Impact | Use Case |
|------|-----------------|---------------|----------|
| **Single** | `translations.ts` | No tree-shaking | Small projects |
| **Module** | `vendors.translations.ts` with multiple exports | Good tree-shaking | **Recommended** |
| **Granular** | `vendors/actions.ts`, `vendors/forms.ts` | Best tree-shaking | Large projects |

#### Module Mode Exports
```typescript
// resources/js/data/translations/vendors.translations.ts
export const VendorsActionsTranslations = {
  en: { create: 'Create', edit: 'Edit' },
  bg: { create: 'Създай', edit: 'Редактирай' }
} as const;

export const VendorsFormsTranslations = {
  en: { title: 'Vendor Form' },
  bg: { title: 'Форма за доставчик' }
} as const;
```

## Type Generation

Generate TypeScript types from your Laravel translations:

```bash
php artisan translations:generate [options]
```

### Options

| Option | Values | Description |
|--------|--------|-------------|
| `--mode` | single, module, granular | Output structure |
| `--locale` | en,bg,de | Specific locales to scan |
| `--fresh` | - | Clear cache before generation |
| `--debug` | - | Show detailed output |

## Translation Export

Export actual translation data for frontend usage:

```bash
php artisan translations:export [options]
```

### Options

| Option | Values | Description |
|--------|--------|-------------|
| `--mode` | single, module, granular | File structure |
| `--organize-by` | locale-mapped, locale, module | How to organize locales |
| `--locale` | en,bg,de | Specific locales only |
| `--format` | typescript, json | Output format |
| `--output` | path/to/dir | Custom output directory |

## Hook Usage

### use-inertia-translations (Backend)

For server-driven translations passed via Inertia props:

```typescript
import { useInertiaTranslations } from '@/hooks/use-inertia-translations';
import type { VendorsI18N } from '@/types/translations';

interface PageProps {
  vendor: Vendor;
  // Type-safe translation structure from backend
  translations: {
    vendors: Pick<VendorsI18N, 'forms' | 'validation'>;
    common: { save: string; cancel: string; };
  };
}

function VendorEdit({ vendor }: PageProps) {
  // Hook automatically reads from Inertia page props
  const { t, locale } = useInertiaTranslations<PageProps['translations']>();
  
  return (
    <form>
      <h1>{t('vendors.forms.title')}</h1>
      <button>{t('common.save')}</button>
      
      {/* TypeScript error if key doesn't exist */}
      <span>{t('vendors.list.title')}</span> {/* ❌ Error: 'list' not in type */}
    </form>
  );
}
```

**Pros:**
- Minimal payload (only current locale, only needed keys)
- Type-safe with backend contract
- No unused translations sent

**Cons:**
- No client-side locale switching
- Requires backend changes for new translations

### use-local-translations (Frontend)

For client-side translations with locale switching:

```typescript
import { useLocalTranslations } from '@/hooks/use-local-translations';
import { VendorsFormsTranslations, VendorsActionsTranslations } from '@/data/translations/vendors.translations';

function VendorForm() {
  // Load translation modules with all locales
  const { t, locale, setLocale, availableLocales } = useLocalTranslations({
    forms: VendorsFormsTranslations,
    actions: VendorsActionsTranslations
  });
  
  return (
    <div>
      {/* Dynamic locale switching */}
      <select value={locale} onChange={(e) => setLocale(e.target.value)}>
        {availableLocales.map(loc => (
          <option key={loc} value={loc}>{loc.toUpperCase()}</option>
        ))}
      </select>
      
      <h1>{t('forms.title')}</h1>
      <button>{t('actions.save')}</button>
    </div>
  );
}
```

**Pros:**
- Client-side locale switching
- No backend changes needed
- Tree-shakeable (import only needed modules)

**Cons:**
- Larger bundle (all locales)
- All translations exposed to client

### Combining Both Approaches

Use backend translations for initial render, client translations for dynamic features:

```typescript
import { useInertiaTranslations } from '@/hooks/use-inertia-translations';
import { useLocalTranslations } from '@/hooks/use-local-translations';
import { CommonTranslations } from '@/data/translations/common.translations';

function HybridComponent() {
  // Server translations for page-specific content
  const { t: serverT } = useInertiaTranslations();
  
  // Client translations for dynamic UI elements
  const { t: clientT, setLocale } = useLocalTranslations({
    common: CommonTranslations
  });
  
  return (
    <>
      {/* Server-driven content */}
      <h1>{serverT('vendors.forms.title')}</h1>
      
      {/* Client-driven UI */}
      <LanguageSwitcher onChange={setLocale} />
      <Toast message={clientT('common.saved')} />
    </>
  );
}
```

## Laravel Integration

### Middleware (Global Translations)

Only share truly global translations:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'locale' => app()->getLocale(),
        // ONLY navigation and layout translations
        'translations' => [
            'navigation' => trans('navigation'),
            'common' => [
                'logout' => trans('common.logout'),
                'profile' => trans('common.profile'),
            ]
        ]
    ]);
}
```

### Controllers (Page-Specific)

Send only what the page needs:

```php
// VendorController.php
public function index()
{
    return Inertia::render('Vendors/Index', [
        'vendors' => Vendor::paginate(),
        'translations' => [
            'vendors' => [
                'list' => trans('vendors.list'),
                'filters' => trans('vendors.filters'),
                'actions' => [
                    'create' => trans('vendors.actions.create'),
                    'export' => trans('vendors.actions.export'),
                ]
            ]
        ]
    ]);
}

public function edit(Vendor $vendor)
{
    return Inertia::render('Vendors/Edit', [
        'vendor' => $vendor,
        'translations' => [
            'vendors' => [
                'forms' => trans('vendors.forms'),
                'validation' => trans('vendors.validation'),
            ]
        ]
    ]);
}
```

## Configuration Options

### Complete Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Translation Paths
    |--------------------------------------------------------------------------
    | Paths to scan for translation files. Use :locale placeholder.
    */
    'paths' => [
        'lang/:locale',
        'resources/lang/:locale',
        'Modules/*/Resources/lang/:locale',  // For modular apps
    ],

    /*
    |--------------------------------------------------------------------------
    | Base Language
    |--------------------------------------------------------------------------
    | The primary language to use for type generation.
    */
    'base_language' => env('TRANSLATION_BASE_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Additional Locales
    |--------------------------------------------------------------------------
    | Other locales to scan when exporting translations.
    */
    'locales' => ['en', 'bg', 'de', 'fr'],

    /*
    |--------------------------------------------------------------------------
    | Type Generation Output
    |--------------------------------------------------------------------------
    | Configure how TypeScript types are generated.
    */
    'output' => [
        'path' => 'resources/js/types',
        'mode' => env('TRANSLATION_TYPES_MODE', 'module'),
        'file_name' => 'translations.d.ts',  // For single mode
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Sources
    |--------------------------------------------------------------------------
    | Define specific sources to generate types for.
    | Leave empty to scan all paths.
    */
    'sources' => [
        'vendors' => [
            'path' => 'lang/en/vendors',
            'nested' => true,  // Scan subdirectories
            'ignore' => ['temp.php', 'old/*'],
        ],
        'tasks' => [
            'path' => 'lang/en/tasks',
            'nested' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Generation Settings
    |--------------------------------------------------------------------------
    */
    'type_suffix' => 'I18N',              // Interface suffix
    'export_keys' => true,                // Generate key union types
    'strict_mode' => true,                // Use strict TypeScript
    'preserve_array_keys' => false,       // Keep numeric array keys
    
    /*
    |--------------------------------------------------------------------------
    | Translation Export Settings
    |--------------------------------------------------------------------------
    | Configure how translation data is exported.
    */
    'translation_export' => [
        'path' => 'resources/js/data/translations',
        'mode' => 'module',                    // single, module, granular
        'organize_by' => 'locale-mapped',      // locale-mapped, locale, module
        'format' => 'typescript',              // typescript, json
        'filename_pattern' => '{source}.translations.{ext}',
        'include_empty' => false,              // Include empty translations
        'minify' => env('APP_ENV') === 'production',
    ],

    /*
    |--------------------------------------------------------------------------
    | Files to Ignore
    |--------------------------------------------------------------------------
    | Translation files to skip during scanning.
    */
    'ignore' => [
        'validation.php',      // Laravel validation
        'pagination.php',      // Laravel pagination  
        'passwords.php',       // Laravel passwords
        'auth.php',           // Laravel auth (optional)
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    | Speed up generation with caching.
    */
    'cache' => [
        'enabled' => env('TRANSLATION_CACHE_ENABLED', true),
        'path' => storage_path('app/translations-cache'),
        'ttl' => 3600,  // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Options
    |--------------------------------------------------------------------------
    */
    'advanced' => [
        'use_short_keys' => false,           // Use short key names
        'group_by_feature' => false,         // Group by feature modules
        'generate_enum_types' => false,      // Generate enum types for fixed values
        'auto_discover_packages' => true,    // Scan vendor packages
    ],
];
```

### Environment Variables

Control behavior via `.env`:

```env
# Mode Configuration
TRANSLATION_TYPES_MODE=module
TRANSLATION_EXPORT_MODE=granular
TRANSLATION_BASE_LANGUAGE=en

# Performance
TRANSLATION_CACHE_ENABLED=true

# Debugging
TRANSLATION_DEBUG=false
```

### Per-Environment Configuration

```php
// config/typescript-translations.php
'translation_export' => [
    'minify' => env('APP_ENV') === 'production',
    'path' => env('APP_ENV') === 'local' 
        ? 'resources/js/data/translations'
        : 'public/js/translations',  // CDN in production
],
```

## Performance

### Bundle Size Comparison

| Approach | Initial Load | Locale Switch | Tree-Shaking |
|----------|-------------|---------------|--------------|
| Backend (Inertia) | ~5KB per page | Page reload | N/A |
| Frontend (All) | ~200KB | Instant | ❌ |
| Frontend (Modular) | ~20KB per module | Instant | ✅ |
| Frontend (Dynamic) | 0KB | Lazy load | ✅ |

### Optimization Strategies

1. **Development**: Use all translations for convenience
2. **Production**: Use backend translations + dynamic imports
3. **Hybrid**: Backend for SSR, frontend for interactive features

### Lazy Loading Example

```typescript
// Only load when modal opens
const loadVendorFormTranslations = async () => {
  const { VendorsFormsTranslations } = await import(
    /* webpackChunkName: "translations-vendors-forms" */
    '@/data/translations/vendors/forms'
  );
  return VendorsFormsTranslations;
};
```

## License

MIT. See [LICENSE.md](LICENSE.md) for details.