<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Retrieve last-modified timestamp from S3-compatible object storage via HEAD request.
 */
final class S3LastModified
{
    public function __construct(private readonly Closure $request)
    {
    }

    public function __invoke(string $path): int
    {
        $result = ($this->request)('HEAD', $path);

        if ($result['status'] === 0) {
            throw new UnableToReadException(
                sprintf('cURL error for "%s": %s', $path, $result['error'] ?? 'unknown error')
            );
        }

        if ($result['status'] === 404) {
            throw new FileNotFoundException(sprintf('File not found: "%s"', $path));
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new UnableToReadException(
                sprintf('Unexpected HTTP %d reading last-modified of "%s"', $result['status'], $path)
            );
        }

        $lastModified = $result['headers']['last-modified'] ?? null;

        if ($lastModified === null) {
            throw new UnableToReadException(
                sprintf('Last-Modified header missing for: "%s"', $path)
            );
        }

        $timestamp = strtotime($lastModified);

        if ($timestamp === false) {
            throw new UnableToReadException(
                sprintf('Unable to parse Last-Modified header for: "%s"', $path)
            );
        }

        return $timestamp;
    }
}
