<?php

declare(strict_types=1);

namespace NVL\LaravelTypescriptTranslations\Config;

/**
 * Output configuration value object.
 */
class OutputConfig
{
    /**
     * Create a new output configuration instance.
     *
     * @param string $path
     * @param string $filename
     */
    public function __construct(
        private readonly string $path,
        private readonly string $filename
    ) {}

    /**
     * Get the output path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the output filename.
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Get the full output path.
     *
     * @return string
     */
    public function getFullPath(): string
    {
        $path = rtrim($this->path, '/');
        return base_path($path . '/' . $this->filename);
    }

    /**
     * Get the directory path.
     *
     * @return string
     */
    public function getDirectory(): string
    {
        return dirname($this->getFullPath());
    }
}