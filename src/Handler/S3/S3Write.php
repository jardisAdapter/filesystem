<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Write file contents to S3-compatible object storage.
 */
final class S3Write
{
    public function __construct(private readonly Closure $request)
    {
    }

    public function __invoke(string $path, string $content): void
    {
        $result = ($this->request)('PUT', $path, [], $content);

        if ($result['status'] === 0) {
            throw new UnableToWriteException(
                sprintf('cURL error for "%s": %s', $path, $result['error'] ?? 'unknown error')
            );
        }

        if ($result['status'] === 404) {
            throw new FileNotFoundException(sprintf('File not found: "%s"', $path));
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new UnableToWriteException(
                sprintf('Unexpected HTTP %d writing "%s"', $result['status'], $path)
            );
        }
    }
}
