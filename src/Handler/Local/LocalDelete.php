<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToDeleteException;

/**
 * Delete a file from the local filesystem.
 */
final class LocalDelete
{
    public function __construct(private readonly string $root)
    {
    }

    public function __invoke(string $path): void
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        if (!@unlink($fullPath)) {
            throw new UnableToDeleteException("Unable to delete file: {$path}");
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
