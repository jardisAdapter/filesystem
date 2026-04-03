<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToDeleteException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Delete a directory recursively from the local filesystem.
 */
final class LocalDeleteDirectory
{
    private readonly LocalResolvePath $resolvePath;
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
        $this->resolvePath = new LocalResolvePath($root);
    }

    public function __invoke(string $path): void
    {
        $fullPath = ($this->resolvePath)($path);

        if (!is_dir($fullPath)) {
            throw new FileNotFoundException("Directory not found: {$path}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        $rootLen = strlen($this->root) + 1;

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), $rootLen);

            if ($item->isDir() && !$item->isLink()) {
                if (!@rmdir($item->getPathname())) {
                    throw new UnableToDeleteException(
                        sprintf('Unable to delete directory: "%s"', $relativePath)
                    );
                }
            } else {
                if (!@unlink($item->getPathname())) {
                    throw new UnableToDeleteException(
                        sprintf('Unable to delete file: "%s"', $relativePath)
                    );
                }
            }
        }

        if (!@rmdir($fullPath)) {
            throw new UnableToDeleteException("Unable to delete directory: {$path}");
        }
    }
}
