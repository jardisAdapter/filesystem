<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

/**
 * Check if a file or directory exists on the local filesystem.
 */
final class LocalExists
{
    public function __construct(
        private readonly string $root,
        private readonly bool $followSymlinks,
    ) {
    }

    public function __invoke(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        if (!$this->followSymlinks && is_link($fullPath)) {
            return false;
        }

        return file_exists($fullPath);
    }

    private function fullPath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        return $this->root . '/' . $path;
    }
}
