<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Data;

use JardisSupport\Contract\Filesystem\FileInfoInterface;

/**
 * Immutable file/directory metadata returned by directory listings.
 */
final readonly class FileInfo implements FileInfoInterface
{
    public function __construct(
        private string $path,
        private int $size,
        private int $lastModified,
        private bool $isFile,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function lastModified(): int
    {
        return $this->lastModified;
    }

    public function isFile(): bool
    {
        return $this->isFile;
    }

    public function isDirectory(): bool
    {
        return !$this->isFile;
    }
}
