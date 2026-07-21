<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use JardisAdapter\Filesystem\Config\S3Config;

/**
 * Build the S3 object key from a relative path and the configured prefix.
 */
final class S3BuildKey
{
    public function __construct(private readonly S3Config $config)
    {
    }

    public function __invoke(string $path): string
    {
        $prefix = $this->config->prefix;

        if ($prefix !== '' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        return $prefix . ltrim($path, '/');
    }
}
