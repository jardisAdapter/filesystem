<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Read a file as a stream from the local filesystem.
 */
final class LocalReadStream
{
    private readonly LocalResolvePath $resolvePath;

    public function __construct(string $root)
    {
        $this->resolvePath = new LocalResolvePath($root);
    }

    /**
     * @return resource
     */
    public function __invoke(string $path)
    {
        $fullPath = ($this->resolvePath)($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $resource = @fopen($fullPath, 'rb');

        if ($resource === false) {
            throw new UnableToReadException("Unable to read file: {$path}");
        }

        return $resource;
    }
}
