<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FilesystemException;

/**
 * Resolve a relative path to an absolute path within the storage root.
 *
 * Performs symlink-based containment check: the resolved path must
 * remain within the root directory. Prevents symlink escape attacks.
 */
final class LocalFullPath
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function __invoke(string $path): string
    {
        $fullPath = $path === '' ? $this->root : $this->root . '/' . $path;

        $resolved = realpath($fullPath);

        if ($resolved === false) {
            return $fullPath;
        }

        if ($resolved !== $this->root && !str_starts_with($resolved, $this->root . '/')) {
            throw new FilesystemException(
                sprintf('Path escapes storage root: "%s"', $path)
            );
        }

        return $fullPath;
    }
}
