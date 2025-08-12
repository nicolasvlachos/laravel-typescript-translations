import { usePage } from '@inertiajs/react';
import * as React from 'react';

/**
 * Generate dot notation paths for translation objects
 */
type DotNotationPaths<T, Prefix extends string = ''> = T extends object
    ? {
        [K in keyof T]: T[K] extends string
            ? Prefix extends ''
                ? `${string & K}`
                : `${Prefix}.${string & K}`
            : T[K] extends object
                ? Prefix extends ''
                    ? `${string & K}` | DotNotationPaths<T[K], `${string & K}`>
                    : `${Prefix}.${string & K}` | DotNotationPaths<T[K], `${Prefix}.${string & K}`>
                : never
    }[keyof T]
    : never;


/**
 * Extract available locales from modules
 */
type ExtractLocales<T> = T extends Record<infer K, unknown> ? K : never;

/**
 * Infer the translations type for a given locale
 */
type InferLocaleTranslations<
    TModules extends Record<string, Record<string, unknown>>,
    TLocale extends string = string
> = {
    [K in keyof TModules]: TModules[K] extends Record<TLocale, infer V> ? V : never
};

/**
 * Options for useTranslations hook
 */
interface UseTranslationsOptions {
    locale?: string;
    fallbackLocale?: string;
    debug?: boolean;
}

/**
 * Main translations hook for locale-mapped translation objects
 * 
 * @example
 * ```typescript
 * // Import your generated locale-mapped translations
 * import { VendorsTranslations } from '@/data/translations/vendors';
 * import { SystemTranslations } from '@/data/translations/system';
 * 
 * function MyComponent() {
 *   const { t, locale } = useTranslations({
 *     vendors: VendorsTranslations,
 *     system: SystemTranslations
 *   });
 * 
 *   return <h1>{t('vendors.forms.title')}</h1>;
 * }
 * ```
 */
export function useLocalTranslations<
    TModules extends Record<string, Record<string, unknown>>,
    TLocale extends ExtractLocales<TModules[keyof TModules]> = ExtractLocales<TModules[keyof TModules]>
>(
    modules: TModules,
    options?: UseTranslationsOptions
): {
    t: TranslationFunction<TModules, TLocale>;
    locale: TLocale;
    translations: InferLocaleTranslations<TModules, TLocale>;
    setLocale: (locale: TLocale) => void;
    availableLocales: TLocale[];
} {
    const { props } = usePage<{ locale?: string; fallback_locale?: string }>();
    
    const [overrideLocale, setOverrideLocale] = React.useState<TLocale | null>(null);
    const locale = (overrideLocale || options?.locale || props.locale || 'en') as TLocale;
    const fallbackLocale = options?.fallbackLocale || props.fallback_locale || 'en';

    // Build translations for current locale
    const translations = React.useMemo(() => {
        const result: Record<string, unknown> = {};
        
        for (const [key, moduleTranslations] of Object.entries(modules)) {
            const module = moduleTranslations as Record<string, unknown>;
            if (module[locale as string]) {
                result[key] = module[locale as string];
            } else if (module[fallbackLocale]) {
                result[key] = module[fallbackLocale];
            } else {
                const firstLocale = Object.keys(module)[0];
                if (firstLocale) {
                    result[key] = module[firstLocale];
                }
            }
        }
        
        return result as InferLocaleTranslations<TModules, TLocale>;
    }, [modules, locale, fallbackLocale]);

    // Get available locales
    const availableLocales = React.useMemo(() => {
        const locales = new Set<string>();
        Object.values(modules).forEach((moduleTranslations) => {
            const module = moduleTranslations as Record<string, unknown>;
            Object.keys(module).forEach(l => locales.add(l));
        });
        return Array.from(locales) as TLocale[];
    }, [modules]);

    // Create translation function
    const t = React.useMemo(
        () => createTranslationFunction(translations, locale, modules, options?.debug),
        [translations, locale, modules, options?.debug]
    );

    return { t, locale, translations, setLocale: setOverrideLocale, availableLocales };
}

/**
 * Translation function type with additional methods
 */
interface TranslationFunction<
    TModules extends Record<string, Record<string, unknown>>,
    TLocale extends string
> {
    <Path extends DotNotationPaths<InferLocaleTranslations<TModules, TLocale>>>(
        path: Path,
        replacements?: Record<string, string | number>
    ): string;
    
    preview<Path extends DotNotationPaths<InferLocaleTranslations<TModules, TLocale>>>(
        path: Path
    ): Record<string, string | undefined>;
    
    debug<Path extends DotNotationPaths<InferLocaleTranslations<TModules, TLocale>>>(
        path: Path
    ): {
        path: Path;
        locale: TLocale;
        value: string;
        allLocales: Record<string, string | undefined>;
    };
}

/**
 * Create the translation function with all its methods
 */
function createTranslationFunction<
    TModules extends Record<string, Record<string, unknown>>,
    TLocale extends string
>(
    translations: InferLocaleTranslations<TModules, TLocale>,
    locale: TLocale,
    modules: TModules,
    debug?: boolean
): TranslationFunction<TModules, TLocale> {
    
    const getByPath = (obj: unknown, path: string): unknown => {
        const keys = path.split('.');
        let result: unknown = obj;
        
        for (const key of keys) {
            if (result == null || typeof result !== 'object') return undefined;
            result = (result as Record<string, unknown>)[key];
        }
        
        return result;
    };

    const replacePlaceholders = (text: string, replacements?: Record<string, string | number>): string => {
        if (!replacements) return text;
        
        let result = text;
        for (const [key, value] of Object.entries(replacements)) {
            result = result
                .replace(new RegExp(`:${key}`, 'g'), String(value))
                .replace(new RegExp(`\\{${key}\\}`, 'g'), String(value))
                .replace(new RegExp(`\\{\\{${key}\\}\\}`, 'g'), String(value));
        }
        return result;
    };

    // Main translation function
    const t = (path: string, replacements?: Record<string, string | number>): string => {
        if (debug) console.log(`[t] ${path} (${locale})`);
        
        const result = getByPath(translations, path);
        if (typeof result === 'string') {
            return replacePlaceholders(result, replacements);
        }
        
        console.warn(`Translation not found: ${path}`);
        return path;
    };

    // Preview function - shows all locales
    t.preview = (path: string): Record<string, string | undefined> => {
        const result: Record<string, string | undefined> = {};
        
        // Get first module to find available locales
        const firstModule = Object.values(modules)[0] as Record<string, unknown> | undefined;
        if (!firstModule) return result;
        
        for (const availableLocale of Object.keys(firstModule)) {
            const localeTranslations: Record<string, unknown> = {};
            for (const [key, moduleTranslations] of Object.entries(modules)) {
                const module = moduleTranslations as Record<string, unknown>;
                if (module[availableLocale]) {
                    localeTranslations[key] = module[availableLocale];
                }
            }
            const value = getByPath(localeTranslations, path);
            result[availableLocale] = typeof value === 'string' ? value : undefined;
        }
        
        return result;
    };

    // Debug function
    t.debug = (path: string) => ({
        path,
        locale,
        value: t(path),
        allLocales: t.preview(path)
    });

    return t as TranslationFunction<TModules, TLocale>;
}

// Type for locale-mapped translation objects
export type LocaleMappedTranslations<T = unknown> = Record<string, T>;

// Export other types
export type { 
    ExtractLocales,
    DotNotationPaths,
    InferLocaleTranslations,
    TranslationFunction
};