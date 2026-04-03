<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Exception;

use JardisSupport\Contract\Filesystem\FilesystemExceptionInterface;
use RuntimeException;

/**
 * Base exception for all filesystem errors.
 */
class FilesystemException extends RuntimeException implements FilesystemExceptionInterface
{
}
