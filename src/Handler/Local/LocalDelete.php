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
    private readonly LocalResolvePath $resolvePath;

    public function __construct(string $root)
    {
        $this->resolvePath = new LocalResolvePath($root);
    }

    public function __invoke(string $path): void
    {
        $fullPath = ($this->resolvePath)($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        if (!@unlink($fullPath)) {
            throw new UnableToDeleteException("Unable to delete file: {$path}");
        }
    }
}
