import { usePage } from '@inertiajs/react';

// Type to generate all possible dot notation paths for translation objects
type TranslationPaths<T> = T extends object
    ? {
        [K in keyof T]: T[K] extends string
            ? `${string & K}`
            : T[K] extends object
                ? `${string & K}` | `${string & K}.${TranslationPaths<T[K]>}`
                : never
    }[keyof T]
    : never;

export function useTranslations<T extends Record<string, unknown>>(): {
    t: (path: TranslationPaths<T>) => string;
    locale: string;
} {
    const { props } = usePage<{
        translations: T;
        locale?: string;
    }>();

    const translations = props.translations;
    const locale = props.locale || 'en';

    const t = (path: TranslationPaths<T>): string => {
        const keys = path.split('.');
        let result: unknown = translations;

        for (const key of keys) {
            if (result == null || typeof result !== 'object') {
                return path;
            }
            result = (result as Record<string, unknown>)[key];
        }

        return typeof result === 'string' ? result : path;
    };

    return { t, locale };
}
