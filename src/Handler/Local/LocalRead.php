<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Read a file from the local filesystem.
 */
final class LocalRead
{
    public function __construct(private readonly string $root)
    {
    }

    public function __invoke(string $path): string
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $content = @file_get_contents($fullPath);

        if ($content === false) {
            throw new UnableToReadException("Unable to read file: {$path}");
        }

        return $content;
    }

    private function fullPath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        return $this->root . '/' . $path;
    }
}
