<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem;

use JardisAdapter\Filesystem\Config\LocalConfig;
use JardisAdapter\Filesystem\Config\S3Config;
use JardisSupport\Contract\Filesystem\FilesystemInterface;

/**
 * Factory service for creating Filesystem instances.
 *
 * Available via the resource chain: $this->getResource()->filesystem()->create(...)
 */
final class FilesystemService
{
    public function create(LocalConfig|S3Config $config): FilesystemInterface
    {
        return new Filesystem($config);
    }
}
