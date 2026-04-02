<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Retrieve MIME type from S3-compatible object storage via HEAD request.
 */
final class S3MimeType
{
    public function __construct(private readonly Closure $request)
    {
    }

    public function __invoke(string $path): string
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
                sprintf('Unexpected HTTP %d reading MIME type of "%s"', $result['status'], $path)
            );
        }

        $contentType = $result['headers']['content-type'] ?? null;

        if ($contentType === null) {
            throw new UnableToReadException(
                sprintf('Content-Type header missing for: "%s"', $path)
            );
        }

        return $contentType;
    }
}
