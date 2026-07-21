<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;

/**
 * Move (copy + delete) a file within S3-compatible object storage.
 */
final class S3Move
{
    public function __construct(
        private readonly Closure $copy,
        private readonly Closure $delete,
    ) {
    }

    public function __invoke(string $source, string $destination): void
    {
        ($this->copy)($source, $destination);
        ($this->delete)($source);
    }
}
