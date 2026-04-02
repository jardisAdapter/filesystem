<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Create a virtual directory in S3-compatible object storage by writing an empty key ending with /.
 */
final class S3CreateDirectory
{
    public function __construct(private readonly Closure $request)
    {
    }

    public function __invoke(string $path): void
    {
        $dirPath = rtrim($path, '/') . '/';

        $result = ($this->request)('PUT', $dirPath, [], '');

        if ($result['status'] === 0) {
            throw new UnableToWriteException(
                sprintf('cURL error for "%s": %s', $path, $result['error'] ?? 'unknown error')
            );
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new UnableToWriteException(
                sprintf('Unexpected HTTP %d creating directory "%s"', $result['status'], $path)
            );
        }
    }
}
