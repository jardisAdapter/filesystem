<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Create a directory on the local filesystem.
 */
final class LocalCreateDirectory
{
    private readonly LocalResolvePath $resolvePath;

    public function __construct(string $root, private readonly int $dirPermissions)
    {
        $this->resolvePath = new LocalResolvePath($root);
    }

    public function __invoke(string $path): void
    {
        $fullPath = ($this->resolvePath)($path);

        if (is_dir($fullPath)) {
            return;
        }

        if (!@mkdir($fullPath, $this->dirPermissions, true) && !is_dir($fullPath)) {
            throw new UnableToWriteException("Unable to create directory: {$path}");
        }
    }
}
