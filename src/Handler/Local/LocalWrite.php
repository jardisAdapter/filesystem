<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Write content to a file on the local filesystem.
 */
final class LocalWrite
{
    public function __construct(
        private readonly string $root,
        private readonly int $filePermissions,
        private readonly int $dirPermissions,
    ) {
    }

    public function __invoke(string $path, string $content): void
    {
        $fullPath = $this->fullPath($path);
        $this->ensureDirectory(dirname($fullPath));

        $result = @file_put_contents($fullPath, $content);

        if ($result === false) {
            throw new UnableToWriteException("Unable to write file: {$path}");
        }

        @chmod($fullPath, $this->filePermissions);
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
