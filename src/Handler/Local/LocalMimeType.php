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
    public function __construct(private readonly string $root)
    {
    }

    public function __invoke(string $path): string
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $mimeType = @mime_content_type($fullPath);

        if ($mimeType === false) {
            throw new UnableToReadException("Unable to read MIME type: {$path}");
        }

        return $mimeType;
    }

    private function fullPath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        return $this->root . '/' . $path;
    }
}
