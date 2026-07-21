<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Get the size of a file on the local filesystem.
 */
final class LocalSize
{
    private readonly LocalResolvePath $resolvePath;

    public function __construct(string $root)
    {
        $this->resolvePath = new LocalResolvePath($root);
    }

    public function __invoke(string $path): int
    {
        $fullPath = ($this->resolvePath)($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $size = @filesize($fullPath);

        if ($size === false) {
            throw new UnableToReadException("Unable to read file size: {$path}");
        }

        return $size;
    }
}
