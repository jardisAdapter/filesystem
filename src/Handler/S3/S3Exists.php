<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Check whether a file exists in S3-compatible object storage.
 */
final class S3Exists
{
    public function __construct(private readonly Closure $request)
    {
    }

    public function __invoke(string $path): bool
    {
        $result = ($this->request)('HEAD', $path);

        if ($result['status'] === 0) {
            throw new UnableToReadException(
                sprintf('cURL error for "%s": %s', $path, $result['error'] ?? 'unknown error')
            );
        }

        if ($result['status'] === 200) {
            return true;
        }

        if ($result['status'] === 404) {
            return false;
        }

        throw new UnableToReadException(
            sprintf('Unexpected HTTP %d checking existence of "%s"', $result['status'], $path)
        );
    }
}
