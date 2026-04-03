<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Exception\UnableToWriteException;

/**
 * Write a stream to S3-compatible object storage.
 */
final class S3WriteStream
{
    public function __construct(private readonly Closure $request)
    {
    }

    /**
     * @param resource $resource
     */
    public function __invoke(string $path, mixed $resource): void
    {
        if (!is_resource($resource)) {
            throw new UnableToWriteException('Expected a valid stream resource');
        }

        $content = stream_get_contents($resource);

        if ($content === false) {
            throw new UnableToWriteException(
                sprintf('Unable to read stream contents for: "%s"', $path)
            );
        }

        $result = ($this->request)('PUT', $path, [], $content);

        if ($result['status'] === 0) {
            throw new UnableToWriteException(
                sprintf('cURL error for "%s": %s', $path, $result['error'] ?? 'unknown error')
            );
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new UnableToWriteException(
                sprintf('Unexpected HTTP %d writing "%s"', $result['status'], $path)
            );
        }
    }
}
