<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Config;

use JardisAdapter\Filesystem\Exception\FilesystemException;

/**
 * Configuration for the local filesystem adapter.
 *
 * Resolves and validates the root directory at construction time.
 */
final readonly class LocalConfig
{
    public string $root;

    public function __construct(
        string $root,
        public int $filePermissions = 0644,
        public int $dirPermissions = 0755,
        public bool $followSymlinks = true,
        public int $publicFilePerms = 0644,
        public int $privateFilePerms = 0600,
        public int $publicDirPerms = 0755,
        public int $privateDirPerms = 0700,
    ) {
        $resolved = realpath($root);

        if ($resolved === false || !is_dir($resolved)) {
            throw new FilesystemException(
                sprintf('Root directory does not exist or is not accessible: "%s"', $root)
            );
        }

        $this->root = $resolved;
    }
}
