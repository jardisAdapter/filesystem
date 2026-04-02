<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem;

use Closure;
use JardisAdapter\Filesystem\Config\LocalConfig;
use JardisAdapter\Filesystem\Config\S3Config;
use JardisAdapter\Filesystem\Handler\Local\LocalCopy;
use JardisAdapter\Filesystem\Handler\Local\LocalCreateDirectory;
use JardisAdapter\Filesystem\Handler\Local\LocalDelete;
use JardisAdapter\Filesystem\Handler\Local\LocalDeleteDirectory;
use JardisAdapter\Filesystem\Handler\Local\LocalExists;
use JardisAdapter\Filesystem\Handler\Local\LocalFullPath;
use JardisAdapter\Filesystem\Handler\Local\LocalGetVisibility;
use JardisAdapter\Filesystem\Handler\Local\LocalLastModified;
use JardisAdapter\Filesystem\Handler\Local\LocalListContents;
use JardisAdapter\Filesystem\Handler\Local\LocalMimeType;
use JardisAdapter\Filesystem\Handler\Local\LocalMove;
use JardisAdapter\Filesystem\Handler\Local\LocalRead;
use JardisAdapter\Filesystem\Handler\Local\LocalReadStream;
use JardisAdapter\Filesystem\Handler\Local\LocalSetVisibility;
use JardisAdapter\Filesystem\Handler\Local\LocalSize;
use JardisAdapter\Filesystem\Handler\Local\LocalWrite;
use JardisAdapter\Filesystem\Handler\Local\LocalWriteStream;
use JardisAdapter\Filesystem\Handler\PathNormalizer;
use JardisAdapter\Filesystem\Handler\S3\S3Copy;
use JardisAdapter\Filesystem\Handler\S3\S3CreateDirectory;
use JardisAdapter\Filesystem\Handler\S3\S3Delete;
use JardisAdapter\Filesystem\Handler\S3\S3DeleteDirectory;
use JardisAdapter\Filesystem\Handler\S3\S3Exists;
use JardisAdapter\Filesystem\Handler\S3\S3GetVisibility;
use JardisAdapter\Filesystem\Handler\S3\S3LastModified;
use JardisAdapter\Filesystem\Handler\S3\S3ListContents;
use JardisAdapter\Filesystem\Handler\S3\S3MimeType;
use JardisAdapter\Filesystem\Handler\S3\S3Move;
use JardisAdapter\Filesystem\Handler\S3\S3Read;
use JardisAdapter\Filesystem\Handler\S3\S3ReadStream;
use JardisAdapter\Filesystem\Handler\S3\S3Request;
use JardisAdapter\Filesystem\Handler\S3\S3SetVisibility;
use JardisAdapter\Filesystem\Handler\S3\S3Size;
use JardisAdapter\Filesystem\Handler\S3\S3Write;
use JardisAdapter\Filesystem\Handler\S3\S3WriteStream;
use JardisAdapter\Filesystem\Handler\S3Signer;
use JardisSupport\Contract\Filesystem\FileInfoInterface;
use JardisSupport\Contract\Filesystem\FilesystemInterface;

/**
 * Filesystem orchestrator.
 *
 * Builds atomic handler closures from configuration and delegates all operations.
 * Each handler is a single-purpose invokable — one __invoke, one closure.
 */
final class Filesystem implements FilesystemInterface
{
    /** @var Closure(string): string */
    private Closure $normalizePath;

    /** @var Closure(string): string */
    private Closure $readFn;

    /** @var Closure(string): resource */
    private Closure $readStreamFn;

    /** @var Closure(string): bool */
    private Closure $existsFn;

    /** @var Closure(string): int */
    private Closure $sizeFn;

    /** @var Closure(string): int */
    private Closure $lastModifiedFn;

    /** @var Closure(string): string */
    private Closure $mimeTypeFn;

    /** @var Closure(string, bool): iterable<FileInfoInterface> */
    private Closure $listContentsFn;

    /** @var Closure(string, string): void */
    private Closure $writeFn;

    /** @var Closure(string, mixed): void */
    private Closure $writeStreamFn;

    /** @var Closure(string): void */
    private Closure $deleteFn;

    /** @var Closure(string, string): void */
    private Closure $copyFn;

    /** @var Closure(string, string): void */
    private Closure $moveFn;

    /** @var Closure(string): void */
    private Closure $createDirectoryFn;

    /** @var Closure(string): void */
    private Closure $deleteDirectoryFn;

    /** @var Closure(string): string */
    private Closure $getVisibilityFn;

    /** @var Closure(string, string): void */
    private Closure $setVisibilityFn;

    public function __construct(LocalConfig|S3Config $config)
    {
        $this->normalizePath = (new PathNormalizer())->__invoke(...);

        match (true) {
            $config instanceof LocalConfig => $this->buildLocal($config),
            $config instanceof S3Config => $this->buildS3($config),
        };
    }

    public function read(string $path): string
    {
        return ($this->readFn)(($this->normalizePath)($path));
    }

    public function readStream(string $path)
    {
        return ($this->readStreamFn)(($this->normalizePath)($path));
    }

    public function exists(string $path): bool
    {
        return ($this->existsFn)(($this->normalizePath)($path));
    }

    public function size(string $path): int
    {
        return ($this->sizeFn)(($this->normalizePath)($path));
    }

    public function lastModified(string $path): int
    {
        return ($this->lastModifiedFn)(($this->normalizePath)($path));
    }

    public function mimeType(string $path): string
    {
        return ($this->mimeTypeFn)(($this->normalizePath)($path));
    }

    public function listContents(string $path, bool $recursive = false): iterable
    {
        return ($this->listContentsFn)(($this->normalizePath)($path), $recursive);
    }

    public function write(string $path, string $content): void
    {
        ($this->writeFn)(($this->normalizePath)($path), $content);
    }

    public function writeStream(string $path, $resource): void
    {
        ($this->writeStreamFn)(($this->normalizePath)($path), $resource);
    }

    public function delete(string $path): void
    {
        ($this->deleteFn)(($this->normalizePath)($path));
    }

    public function copy(string $source, string $destination): void
    {
        ($this->copyFn)(
            ($this->normalizePath)($source),
            ($this->normalizePath)($destination)
        );
    }

    public function move(string $source, string $destination): void
    {
        ($this->moveFn)(
            ($this->normalizePath)($source),
            ($this->normalizePath)($destination)
        );
    }

    public function createDirectory(string $path): void
    {
        ($this->createDirectoryFn)(($this->normalizePath)($path));
    }

    public function deleteDirectory(string $path): void
    {
        ($this->deleteDirectoryFn)(($this->normalizePath)($path));
    }

    public function getVisibility(string $path): string
    {
        return ($this->getVisibilityFn)(($this->normalizePath)($path));
    }

    public function setVisibility(string $path, string $visibility): void
    {
        ($this->setVisibilityFn)(($this->normalizePath)($path), $visibility);
    }

    private function buildLocal(LocalConfig $config): void
    {
        $root = $config->root;
        $fp = $config->filePermissions;
        $dp = $config->dirPermissions;

        $checkContainment = (new LocalFullPath($root))->__invoke(...);
        $normalize = $this->normalizePath;
        $this->normalizePath = static function (string $path) use ($normalize, $checkContainment): string {
            $normalized = $normalize($path);
            $checkContainment($normalized);

            return $normalized;
        };

        $this->readFn = (new LocalRead($root))->__invoke(...);
        $this->readStreamFn = (new LocalReadStream($root))->__invoke(...);
        $this->existsFn = (new LocalExists($root, $config->followSymlinks))->__invoke(...);
        $this->sizeFn = (new LocalSize($root))->__invoke(...);
        $this->lastModifiedFn = (new LocalLastModified($root))->__invoke(...);
        $this->mimeTypeFn = (new LocalMimeType($root))->__invoke(...);
        $this->listContentsFn = (new LocalListContents($root, $config->followSymlinks))->__invoke(...);

        $this->writeFn = (new LocalWrite($root, $fp, $dp))->__invoke(...);
        $this->writeStreamFn = (new LocalWriteStream($root, $fp, $dp))->__invoke(...);
        $this->deleteFn = (new LocalDelete($root))->__invoke(...);
        $this->copyFn = (new LocalCopy($root, $fp, $dp))->__invoke(...);
        $this->moveFn = (new LocalMove($root, $dp))->__invoke(...);
        $this->createDirectoryFn = (new LocalCreateDirectory($root, $dp))->__invoke(...);
        $this->deleteDirectoryFn = (new LocalDeleteDirectory($root))->__invoke(...);

        $this->getVisibilityFn = (new LocalGetVisibility(
            $root,
            $config->publicFilePerms,
            $config->publicDirPerms,
        ))->__invoke(...);

        $this->setVisibilityFn = (new LocalSetVisibility(
            $root,
            $config->publicFilePerms,
            $config->privateFilePerms,
            $config->publicDirPerms,
            $config->privateDirPerms,
        ))->__invoke(...);
    }

    private function buildS3(S3Config $config): void
    {
        $sign = (new S3Signer($config))->sign(...);
        $request = (new S3Request($config, $sign))->__invoke(...);

        $this->readFn = (new S3Read($request))->__invoke(...);
        $this->readStreamFn = (new S3ReadStream($config, $sign))->__invoke(...);
        $this->existsFn = (new S3Exists($request))->__invoke(...);
        $this->sizeFn = (new S3Size($request))->__invoke(...);
        $this->lastModifiedFn = (new S3LastModified($request))->__invoke(...);
        $this->mimeTypeFn = (new S3MimeType($request))->__invoke(...);
        $this->listContentsFn = (new S3ListContents($config, $sign))->__invoke(...);

        $this->writeFn = (new S3Write($request))->__invoke(...);
        $this->writeStreamFn = (new S3WriteStream($request))->__invoke(...);
        $deleteFn = (new S3Delete($request))->__invoke(...);
        $this->deleteFn = $deleteFn;
        $copyFn = (new S3Copy($config, $request))->__invoke(...);
        $this->copyFn = $copyFn;
        $this->moveFn = (new S3Move($copyFn, $deleteFn))->__invoke(...);
        $this->createDirectoryFn = (new S3CreateDirectory($request))->__invoke(...);
        $this->deleteDirectoryFn = (new S3DeleteDirectory($config, $sign))->__invoke(...);

        $this->getVisibilityFn = (new S3GetVisibility($config, $sign))->__invoke(...);
        $this->setVisibilityFn = (new S3SetVisibility($config, $sign))->__invoke(...);
    }
}
