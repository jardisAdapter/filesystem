<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\FilesystemException;

/**
 * Set the visibility of a file or directory via Unix permissions.
 */
final class LocalSetVisibility
{
    public function __construct(
        private readonly string $root,
        private readonly int $publicFilePerms,
        private readonly int $privateFilePerms,
        private readonly int $publicDirPerms,
        private readonly int $privateDirPerms,
    ) {
    }

    public function __invoke(string $path, string $visibility): void
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $isDirectory = is_dir($fullPath);

        $permissions = match ($visibility) {
            'public' => $isDirectory ? $this->publicDirPerms : $this->publicFilePerms,
            'private' => $isDirectory ? $this->privateDirPerms : $this->privateFilePerms,
            default => throw new FilesystemException("Invalid visibility: {$visibility}"),
        };

        if (!@chmod($fullPath, $permissions)) {
            throw new FilesystemException("Unable to set visibility on: {$path}");
        }
    }

    private function fullPath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        return $this->root . '/' . $path;
    }
}
