<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem;

use JardisAdapter\Filesystem\Config\LocalConfig;
use JardisAdapter\Filesystem\Config\S3Config;
use JardisSupport\Contract\Filesystem\FilesystemInterface;
use JardisSupport\Contract\Filesystem\FilesystemServiceInterface;

/**
 * Factory service for creating Filesystem instances.
 *
 * Implements the contract interface (local/s3) for injection via resource chain.
 * Additionally provides create() for advanced configuration with full Config objects.
 */
final class FilesystemService implements FilesystemServiceInterface
{
    public function local(string $root): FilesystemInterface
    {
        return new Filesystem(new LocalConfig($root));
    }

    public function s3(
        string $bucket,
        string $region,
        string $key,
        #[\SensitiveParameter]
        string $secret,
        string $endpoint = 'https://s3.amazonaws.com',
        string $prefix = '',
    ): FilesystemInterface {
        return new Filesystem(new S3Config($bucket, $region, $key, $secret, $endpoint, $prefix));
    }

    /**
     * Create a filesystem instance with full configuration control.
     *
     * Use this when you need custom permissions, symlink settings,
     * or other advanced options not exposed by local()/s3().
     */
    public function create(LocalConfig|S3Config $config): FilesystemInterface
    {
        return new Filesystem($config);
    }
}
