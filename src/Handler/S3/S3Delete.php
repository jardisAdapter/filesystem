<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToDeleteException;

/**
 * Delete a file from S3-compatible object storage.
 */
final class S3Delete
{
    public function __construct(private readonly Closure $request)
    {
    }

    public function __invoke(string $path): void
    {
        $result = ($this->request)('DELETE', $path);

        if ($result['status'] === 0) {
            throw new UnableToDeleteException(
                sprintf('cURL error for "%s": %s', $path, $result['error'] ?? 'unknown error')
            );
        }

        if ($result['status'] === 404) {
            throw new FileNotFoundException(sprintf('File not found: "%s"', $path));
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new UnableToDeleteException(
                sprintf('Unexpected HTTP %d deleting "%s"', $result['status'], $path)
            );
        }
    }
}
