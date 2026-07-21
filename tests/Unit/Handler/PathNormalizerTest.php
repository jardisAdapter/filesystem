<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Handler;

use JardisAdapter\Filesystem\Exception\FilesystemException;
use JardisAdapter\Filesystem\Handler\PathNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Handler\PathNormalizer
 */
final class PathNormalizerTest extends TestCase
{
    private PathNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PathNormalizer();
    }

    public function testNormalizesSimplePath(): void
    {
        $this->assertSame('foo/bar.txt', ($this->normalizer)('foo/bar.txt'));
    }

    public function testStripsLeadingSlash(): void
    {
        $this->assertSame('foo/bar.txt', ($this->normalizer)('/foo/bar.txt'));
    }

    public function testNormalizesDoubleSlashes(): void
    {
        $this->assertSame('foo/bar/baz.txt', ($this->normalizer)('foo//bar///baz.txt'));
    }

    public function testNormalizesBackslashes(): void
    {
        $this->assertSame('foo/bar/baz.txt', ($this->normalizer)('foo\\bar\\baz.txt'));
    }

    public function testResolvesDotSegments(): void
    {
        $this->assertSame('foo/bar.txt', ($this->normalizer)('./foo/./bar.txt'));
    }

    public function testReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', ($this->normalizer)(''));
    }

    public function testReturnsEmptyStringForSlashOnly(): void
    {
        $this->assertSame('', ($this->normalizer)('/'));
    }

    public function testThrowsOnNullByte(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('null byte');

        ($this->normalizer)("foo\x00bar.txt");
    }

    public function testThrowsOnNullByteInDirectory(): void
    {
        $this->expectException(FilesystemException::class);

        ($this->normalizer)("dir/\x00/file.txt");
    }

    public function testThrowsOnPathTraversal(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('Path traversal detected');

        ($this->normalizer)('../etc/passwd');
    }

    public function testThrowsOnPathTraversalInMiddle(): void
    {
        $this->expectException(FilesystemException::class);

        ($this->normalizer)('foo/../../etc/passwd');
    }

    public function testThrowsOnEncodedPathTraversal(): void
    {
        $this->expectException(FilesystemException::class);

        ($this->normalizer)('foo/../bar');
    }
}
