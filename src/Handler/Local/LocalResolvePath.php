<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

/**
 * Resolve a relative path to an absolute path within the storage root.
 *
 * Simple concatenation without symlink containment check.
 * Used internally by individual Local handlers.
 */
final class LocalResolvePath
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function __invoke(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        return $this->root . '/' . $path;
    }
}
