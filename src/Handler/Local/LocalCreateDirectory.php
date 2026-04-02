<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Create a directory on the local filesystem.
 */
final class LocalCreateDirectory
{
    public function __construct(
        private readonly string $root,
        private readonly int $dirPermissions,
    ) {
    }

    public function __invoke(string $path): void
    {
        $fullPath = $this->fullPath($path);

        if (is_dir($fullPath)) {
            return;
        }

        if (!@mkdir($fullPath, $this->dirPermissions, true) && !is_dir($fullPath)) {
            throw new UnableToWriteException("Unable to create directory: {$path}");
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
