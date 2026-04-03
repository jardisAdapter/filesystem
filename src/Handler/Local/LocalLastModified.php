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

        $mtime = @filemtime($fullPath);

        if ($mtime === false) {
            throw new UnableToReadException("Unable to read last modified time: {$path}");
        }

        return $mtime;
    }
}
