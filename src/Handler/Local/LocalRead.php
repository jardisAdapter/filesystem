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
    private readonly LocalResolvePath $resolvePath;

    public function __construct(string $root)
    {
        $this->resolvePath = new LocalResolvePath($root);
    }

    public function __invoke(string $path): string
    {
        $fullPath = ($this->resolvePath)($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $content = @file_get_contents($fullPath);

        if ($content === false) {
            throw new UnableToReadException("Unable to read file: {$path}");
        }

        return $content;
    }
}
