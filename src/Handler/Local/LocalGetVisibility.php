<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\Local;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Get the visibility of a file or directory via Unix permissions.
 */
final class LocalGetVisibility
{
    private readonly LocalResolvePath $resolvePath;

    public function __construct(
        string $root,
        private readonly int $publicFilePerms,
        private readonly int $publicDirPerms,
    ) {
        $this->resolvePath = new LocalResolvePath($root);
    }

    public function __invoke(string $path): string
    {
        $fullPath = ($this->resolvePath)($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $perms = @fileperms($fullPath);

        if ($perms === false) {
            throw new UnableToReadException("Unable to read permissions: {$path}");
        }

        $octalPerms = $perms & 0777;

        if (is_dir($fullPath)) {
            return $octalPerms === $this->publicDirPerms ? 'public' : 'private';
        }

        return $octalPerms === $this->publicFilePerms ? 'public' : 'private';
    }
}
