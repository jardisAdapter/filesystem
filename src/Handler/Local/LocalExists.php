<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

/**
 * Check if a file or directory exists on the local filesystem.
 */
final class LocalExists
{
    private readonly LocalResolvePath $resolvePath;

    public function __construct(string $root, private readonly bool $followSymlinks)
    {
        $this->resolvePath = new LocalResolvePath($root);
    }

    public function __invoke(string $path): bool
    {
        $fullPath = ($this->resolvePath)($path);

        if (!$this->followSymlinks && is_link($fullPath)) {
            return false;
        }

        return file_exists($fullPath);
    }
}
