<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Detect the MIME type of a file on the local filesystem.
 */
final class LocalMimeType
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

        $mimeType = @mime_content_type($fullPath);

        if ($mimeType === false) {
            throw new UnableToReadException("Unable to read MIME type: {$path}");
        }

        return $mimeType;
    }
}
