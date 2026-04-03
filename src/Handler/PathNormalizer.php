<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler;

use JardisAdapter\Filesystem\Exception\FilesystemException;

/**
 * Normalizes and validates filesystem paths.
 *
 * Prevents path traversal attacks, null byte injection,
 * and ensures consistent path format.
 */
final class PathNormalizer
{
    public function __invoke(string $path): string
    {
        if (str_contains($path, "\x00")) {
            throw new FilesystemException('Path contains null byte');
        }

        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if ($path === '') {
            return '';
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new FilesystemException(
                    sprintf('Path traversal detected: "%s"', $path)
                );
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
