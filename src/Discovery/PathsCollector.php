<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Discovery;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NVL\LaravelTypescriptTranslations\Config\TranslationConfig;

/**
 * Collects and discovers translation paths in the application.
 */
class PathsCollector
{
    /**
     * Collected language paths with their sources.
     *
     * @var array<string, array<string>>
     */
    private array $collectedPaths = [];

    /**
     * Create a new paths collector instance.
     *
     * @param TranslationConfig $config
     */
    public function __construct(
        private readonly TranslationConfig $config
    ) {}

    /**
     * Collect all translation paths based on configuration.
     *
     * @return array<string, array<string>>
     */
    public function collect(): array
    {
        $this->collectedPaths = [];

        // Collect configured paths
        foreach ($this->config->getPaths() as $path) {
            $this->discoverPath($path);
        }

        // Collect vendor paths if enabled
        if ($this->config->shouldScanVendor()) {
            $this->collectVendorPaths();
        }

        return $this->collectedPaths;
    }

    /**
     * Discover translation paths in a given directory.
     *
     * @param string $path
     * @return void
     */
    private function discoverPath(string $path): void
    {
        // Check if path contains wildcards
        if (str_contains($path, '*')) {
            $this->discoverWildcardPaths($path);
            return;
        }

        $sourceName = $this->getSourceName($path);
        $basePath = $this->resolvePath($path);

        // Check for direct lang directory
        if (File::exists($basePath . '/lang') && $this->isLangDirectory($basePath . '/lang')) {
            $this->addPath($sourceName, $basePath . '/lang');
        }

        // Check for resources/lang within the path
        if (File::exists($basePath . '/resources/lang') && $this->isLangDirectory($basePath . '/resources/lang')) {
            $this->addPath($sourceName, $basePath . '/resources/lang');
        }

        // Check if the path itself is a lang directory
        if (File::exists($basePath) && $this->isLangDirectory($basePath)) {
            $this->addPath($sourceName, $basePath);
        }
    }

    /**
     * Discover paths using wildcard patterns.
     *
     * @param string $pattern
     * @return void
     */
    private function discoverWildcardPaths(string $pattern): void
    {
        $basePath = base_path();
        $fullPattern = Str::startsWith($pattern, '/') ? $pattern : $basePath . '/' . $pattern;
        
        // Use glob to find matching directories
        $matches = glob($fullPattern, GLOB_ONLYDIR);
        
        if (empty($matches)) {
            return;
        }

        foreach ($matches as $match) {
            // Get relative path from base
            $relativePath = str_replace($basePath . '/', '', $match);
            $sourceName = $this->getSourceName($relativePath);
            
            // Check for lang directories in the matched path
            if (File::exists($match . '/lang') && $this->isLangDirectory($match . '/lang')) {
                $this->addPath($sourceName, $match . '/lang');
            }
            
            if (File::exists($match . '/resources/lang') && $this->isLangDirectory($match . '/resources/lang')) {
                $this->addPath($sourceName, $match . '/resources/lang');
            }
            
            if (File::exists($match . '/Resources/lang') && $this->isLangDirectory($match . '/Resources/lang')) {
                $this->addPath($sourceName, $match . '/Resources/lang');
            }
        }
    }

    /**
     * Collect vendor translation paths.
     *
     * @return void
     */
    private function collectVendorPaths(): void
    {
        // Scan configured vendor paths
        foreach ($this->config->getVendorPaths() as $vendorPath) {
            $this->discoverPath($vendorPath);
        }

        // Auto-discover vendor packages with translations
        $vendorPath = base_path('vendor');
        if (File::exists($vendorPath)) {
            $this->discoverVendorPackages($vendorPath);
        }
    }

    /**
     * Discover translation paths in vendor packages.
     *
     * @param string $vendorPath
     * @return void
     */
    private function discoverVendorPackages(string $vendorPath): void
    {
        $vendors = File::directories($vendorPath);

        foreach ($vendors as $vendor) {
            // Skip non-package directories
            if (in_array(basename($vendor), ['bin', 'composer'])) {
                continue;
            }

            $packages = File::directories($vendor);
            foreach ($packages as $package) {
                $this->discoverVendorPackagePaths($package);
            }
        }
    }

    /**
     * Discover translation paths in a vendor package.
     *
     * @param string $packagePath
     * @return void
     */
    private function discoverVendorPackagePaths(string $packagePath): void
    {
        $possiblePaths = [
            '/resources/lang',
            '/lang',
            '/src/lang',
            '/src/resources/lang',
        ];

        foreach ($possiblePaths as $langPath) {
            $fullPath = $packagePath . $langPath;
            if (File::exists($fullPath) && $this->isLangDirectory($fullPath)) {
                $packageName = $this->getPackageName($packagePath);
                $this->addPath('Vendor/' . $packageName, $fullPath);
            }
        }
    }

    /**
     * Get package name from path.
     *
     * @param string $packagePath
     * @return string
     */
    private function getPackageName(string $packagePath): string
    {
        $segments = explode('/', $packagePath);
        $package = array_slice($segments, -2, 2);
        return Str::studly(implode('/', $package));
    }

    /**
     * Check if a directory is a language directory.
     *
     * @param string $path
     * @return bool
     */
    private function isLangDirectory(string $path): bool
    {
        // Check for locale directories
        $dirs = File::directories($path);
        foreach ($dirs as $dir) {
            $dirName = basename($dir);
            // Common locale patterns
            if (preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $dirName)) {
                return true;
            }
        }

        // Check for JSON locale files
        $jsonFiles = File::glob($path . '/*.json');
        foreach ($jsonFiles as $file) {
            $fileName = basename($file, '.json');
            if (preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $fileName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get source name from path.
     *
     * @param string $path
     * @return string
     */
    private function getSourceName(string $path): string
    {
        // Check if it's a default lang folder path
        if (in_array($path, ['lang', 'resources/lang'])) {
            return 'System';
        }

        // Check if it's a module path
        if (Str::contains($path, '/Modules/') || Str::startsWith($path, 'Modules/')) {
            preg_match('/Modules\/([^\/]+)/', $path, $matches);
            return $matches[1] ?? 'Unknown';
        }

        // Check if it's a vendor path
        if (Str::contains($path, 'vendor/')) {
            return $this->getPackageName($path);
        }

        // For other paths, use the last segment
        $segments = explode('/', trim($path, '/'));
        $name = end($segments);

        // Special cases
        if ($name === 'app' || $name === 'resources') {
            return 'App';
        }

        if ($name === 'packages') {
            return 'Packages';
        }

        return Str::studly($name);
    }

    /**
     * Resolve path to absolute path.
     *
     * @param string $path
     * @return string
     */
    private function resolvePath(string $path): string
    {
        return Str::startsWith($path, '/') ? $path : base_path($path);
    }

    /**
     * Add a path to the collection.
     *
     * @param string $source
     * @param string $path
     * @return void
     */
    private function addPath(string $source, string $path): void
    {
        if (!isset($this->collectedPaths[$source])) {
            $this->collectedPaths[$source] = [];
        }

        if (!in_array($path, $this->collectedPaths[$source])) {
            $this->collectedPaths[$source][] = $path;
        }
    }

    /**
     * Get collected paths for a specific source.
     *
     * @param string $source
     * @return array<string>
     */
    public function getPathsForSource(string $source): array
    {
        return $this->collectedPaths[$source] ?? [];
    }

    /**
     * Get all sources.
     *
     * @return array<string>
     */
    public function getSources(): array
    {
        return array_keys($this->collectedPaths);
    }
}