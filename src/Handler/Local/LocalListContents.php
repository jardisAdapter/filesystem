<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use FilesystemIterator;
use JardisAdapter\Filesystem\Data\FileInfo;
use JardisAdapter\Filesystem\Exception\UnableToReadException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * List directory contents on the local filesystem.
 */
final class LocalListContents
{
    public function __construct(
        private readonly string $root,
        private readonly bool $followSymlinks,
    ) {
    }

    /**
     * @return iterable<FileInfo>
     */
    public function __invoke(string $path, bool $recursive): iterable
    {
        $fullPath = $this->fullPath($path);

        if (!is_dir($fullPath)) {
            throw new UnableToReadException("Directory not found: {$path}");
        }

        $flags = FilesystemIterator::SKIP_DOTS;

        if ($this->followSymlinks) {
            $flags |= FilesystemIterator::FOLLOW_SYMLINKS;
        }

        if ($recursive) {
            $directoryIterator = new RecursiveDirectoryIterator($fullPath, $flags);
            $iterator = new RecursiveIteratorIterator(
                $directoryIterator,
                RecursiveIteratorIterator::SELF_FIRST,
            );
        } else {
            $iterator = new FilesystemIterator($fullPath, $flags);
        }

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$this->followSymlinks && $item->isLink()) {
                continue;
            }

            $relativePath = $this->relativePath($item->getPathname());
            $isFile = $item->isFile();
            $size = $isFile ? (int) $item->getSize() : 0;
            $lastModified = (int) $item->getMTime();

            yield new FileInfo($relativePath, $size, $lastModified, $isFile);
        }
    }

    private function relativePath(string $absolutePath): string
    {
        $rootWithSlash = rtrim($this->root, '/') . '/';

        if (str_starts_with($absolutePath, $rootWithSlash)) {
            return substr($absolutePath, strlen($rootWithSlash));
        }

        return $absolutePath;
    }

    private function fullPath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        return $this->root . '/' . $path;
    }
}
