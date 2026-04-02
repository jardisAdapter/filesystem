<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Copy a file on the local filesystem.
 */
final class LocalCopy
{
    public function __construct(
        private readonly string $root,
        private readonly int $filePermissions,
        private readonly int $dirPermissions,
    ) {
    }

    public function __invoke(string $source, string $destination): void
    {
        $fullSource = $this->fullPath($source);
        $fullDestination = $this->fullPath($destination);

        if (!file_exists($fullSource)) {
            throw new FileNotFoundException("File not found: {$source}");
        }

        $this->ensureDirectory(dirname($fullDestination));

        if (!@copy($fullSource, $fullDestination)) {
            throw new UnableToWriteException("Unable to copy file from {$source} to {$destination}");
        }

        @chmod($fullDestination, $this->filePermissions);
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, $this->dirPermissions, true) && !is_dir($directory)) {
            throw new UnableToWriteException("Unable to create directory: {$directory}");
        }
    }

    private function fullPath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        return $this->root . '/' . $path;
    }
}
