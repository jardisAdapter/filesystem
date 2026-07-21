<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Read file contents from S3-compatible object storage.
 */
final class S3Read
{
    public function __construct(private readonly Closure $request)
    {
    }

    public function __invoke(string $path): string
    {
        $result = ($this->request)('GET', $path);

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
                sprintf('Unexpected HTTP %d reading "%s"', $result['status'], $path)
            );
        }

        return $result['body'];
    }
}
