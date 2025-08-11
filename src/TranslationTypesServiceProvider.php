<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations;

use Illuminate\Support\ServiceProvider;
use NVL\LaravelTypescriptTranslations\Commands\AnalyticsCommand;
use NVL\LaravelTypescriptTranslations\Commands\ExportKeysCommand;
use NVL\LaravelTypescriptTranslations\Commands\ExportTranslationsCommand;
use NVL\LaravelTypescriptTranslations\Commands\GenerateTypesCommand;
use NVL\LaravelTypescriptTranslations\Commands\ScanTranslationsCommand;
use NVL\LaravelTypescriptTranslations\Commands\ValidateTranslationsCommand;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;

/**
 * Service provider for Laravel TypeScript Translations package.
 */
class TranslationTypesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/typescript-translations.php',
            'typescript-translations'
        );

        $this->app->singleton(TranslationConfig::class, function ($app) {
            return new TranslationConfig($app['config']->get('typescript-translations', []));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/typescript-translations.php' => config_path('typescript-translations.php'),
            ], ['typescript-translations', 'typescript-translations-config', 'config']);

            // Publish stubs
            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/typescript-translations'),
            ], ['typescript-translations', 'typescript-translations-stubs', 'stubs']);

            // Publish everything
            $this->publishes([
                __DIR__ . '/../config/typescript-translations.php' => config_path('typescript-translations.php'),
                __DIR__ . '/../stubs' => base_path('stubs/typescript-translations'),
            ], 'typescript-translations');

            // Register commands
            $this->commands([
                GenerateTypesCommand::class,
                ScanTranslationsCommand::class,
                ValidateTranslationsCommand::class,
                ExportKeysCommand::class,
                ExportTranslationsCommand::class,
                AnalyticsCommand::class,
            ]);
        }
    }
}