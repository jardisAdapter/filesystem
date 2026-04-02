<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Config\S3Config;
use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Copy a file within S3-compatible object storage.
 */
final class S3Copy
{
    public function __construct(
        private readonly S3Config $config,
        private readonly Closure $request,
    ) {
    }

    public function __invoke(string $source, string $destination): void
    {
        $sourceKey = $this->buildKey($source);
        $copySource = '/' . $this->config->bucket . '/' . $sourceKey;

        $result = ($this->request)('PUT', $destination, ['x-amz-copy-source' => $copySource], '');

        if ($result['status'] === 0) {
            throw new UnableToWriteException(
                sprintf('cURL error for "%s": %s', $destination, $result['error'] ?? 'unknown error')
            );
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new UnableToWriteException(
                sprintf('Unexpected HTTP %d copying "%s" to "%s"', $result['status'], $source, $destination)
            );
        }
    }

    private function buildKey(string $path): string
    {
        $prefix = $this->config->prefix;

        if ($prefix !== '' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        return $prefix . ltrim($path, '/');
    }
}
