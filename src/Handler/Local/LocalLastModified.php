<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Get the last modification time of a file on the local filesystem.
 */
final class LocalLastModified
{
    public function __construct(private readonly string $root)
    {
    }

    public function __invoke(string $path): int
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $mtime = @filemtime($fullPath);

        if ($mtime === false) {
            throw new UnableToReadException("Unable to read last modified time: {$path}");
        }

        return $mtime;
    }

    private function fullPath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        return $this->root . '/' . $path;
    }
}
