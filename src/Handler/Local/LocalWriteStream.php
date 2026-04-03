<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Write a stream to a file on the local filesystem.
 */
final class LocalWriteStream
{
    private readonly LocalResolvePath $resolvePath;

    public function __construct(
        string $root,
        private readonly int $filePermissions,
        private readonly int $dirPermissions,
    ) {
        $this->resolvePath = new LocalResolvePath($root);
    }

    /**
     * @param resource $resource
     */
    public function __invoke(string $path, mixed $resource): void
    {
        if (!is_resource($resource)) {
            throw new UnableToWriteException('Expected a valid stream resource');
        }

        $fullPath = ($this->resolvePath)($path);
        $this->ensureDirectory(dirname($fullPath));

        $target = @fopen($fullPath, 'wb');

        if ($target === false) {
            throw new UnableToWriteException("Unable to open file for writing: {$path}");
        }

        try {
            $result = @stream_copy_to_stream($resource, $target);

            if ($result === false) {
                throw new UnableToWriteException("Unable to write stream to file: {$path}");
            }
        } finally {
            @fclose($target);
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
}
