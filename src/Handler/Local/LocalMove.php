<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Move a file on the local filesystem.
 */
final class LocalMove
{
    private readonly LocalResolvePath $resolvePath;

    public function __construct(string $root, private readonly int $dirPermissions)
    {
        $this->resolvePath = new LocalResolvePath($root);
    }

    public function __invoke(string $source, string $destination): void
    {
        $fullSource = ($this->resolvePath)($source);
        $fullDestination = ($this->resolvePath)($destination);

        if (!file_exists($fullSource)) {
            throw new FileNotFoundException("File not found: {$source}");
        }

        $this->ensureDirectory(dirname($fullDestination));

        if (!@rename($fullSource, $fullDestination)) {
            throw new UnableToWriteException("Unable to move file from {$source} to {$destination}");
        }
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
}
