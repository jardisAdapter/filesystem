<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Integration;

use JardisAdapter\Filesystem\Config\S3Config;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Filesystem;
use JardisSupport\Contract\Filesystem\FileInfoInterface;
use JardisSupport\Contract\Filesystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Filesystem
 * @covers \JardisAdapter\Filesystem\Handler\S3Signer
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3Request
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3Read
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3ReadStream
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3Write
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3WriteStream
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3Delete
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3Copy
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3Move
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3Exists
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3Size
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3LastModified
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3MimeType
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3ListContents
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3CreateDirectory
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3DeleteDirectory
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3GetVisibility
 * @covers \JardisAdapter\Filesystem\Handler\S3\S3SetVisibility
 */
final class FilesystemS3Test extends TestCase
{
    private Filesystem $fs;
    private string $prefix;

    protected function setUp(): void
    {
        $endpoint = $_ENV['MINIO_ENDPOINT'] ?? getenv('MINIO_ENDPOINT') ?: '';
        $key = $_ENV['MINIO_ACCESS_KEY'] ?? getenv('MINIO_ACCESS_KEY') ?: '';
        $secret = $_ENV['MINIO_SECRET_KEY'] ?? getenv('MINIO_SECRET_KEY') ?: '';
        $bucket = $_ENV['MINIO_BUCKET'] ?? getenv('MINIO_BUCKET') ?: '';
        $region = $_ENV['MINIO_REGION'] ?? getenv('MINIO_REGION') ?: 'us-east-1';

        if ($endpoint === '' || $key === '' || $secret === '' || $bucket === '') {
            $this->markTestSkipped('MinIO not configured (MINIO_ENDPOINT, MINIO_ACCESS_KEY, MINIO_SECRET_KEY, MINIO_BUCKET)');
        }

        $this->prefix = 'test_' . uniqid() . '/';

        $this->fs = new Filesystem(new S3Config(
            bucket: $bucket,
            region: $region,
            key: $key,
            secret: $secret,
            endpoint: $endpoint,
            prefix: $this->prefix,
        ));
    }

    protected function tearDown(): void
    {
        if (!isset($this->fs)) {
            return;
        }

        try {
            $this->fs->deleteDirectory('');
        } catch (\Throwable) {
            // cleanup best-effort
        }
    }

    public function testImplementsFilesystemInterface(): void
    {
        $this->assertInstanceOf(FilesystemInterface::class, $this->fs);
    }

    public function testWriteAndRead(): void
    {
        $this->fs->write('hello.txt', 'hello world');

        $this->assertSame('hello world', $this->fs->read('hello.txt'));
    }

    public function testWriteOverwritesExisting(): void
    {
        $this->fs->write('file.txt', 'old');
        $this->fs->write('file.txt', 'new');

        $this->assertSame('new', $this->fs->read('file.txt'));
    }

    public function testExists(): void
    {
        $this->assertFalse($this->fs->exists('missing.txt'));

        $this->fs->write('present.txt', 'data');

        $this->assertTrue($this->fs->exists('present.txt'));
    }

    public function testReadThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->fs->read('nonexistent.txt');
    }

    public function testDelete(): void
    {
        $this->fs->write('to-delete.txt', 'data');
        $this->fs->delete('to-delete.txt');

        $this->assertFalse($this->fs->exists('to-delete.txt'));
    }

    public function testCopy(): void
    {
        $this->fs->write('source.txt', 'copy me');
        $this->fs->copy('source.txt', 'target.txt');

        $this->assertSame('copy me', $this->fs->read('source.txt'));
        $this->assertSame('copy me', $this->fs->read('target.txt'));
    }

    public function testMove(): void
    {
        $this->fs->write('old-name.txt', 'move me');
        $this->fs->move('old-name.txt', 'new-name.txt');

        $this->assertFalse($this->fs->exists('old-name.txt'));
        $this->assertSame('move me', $this->fs->read('new-name.txt'));
    }

    public function testSize(): void
    {
        $this->fs->write('sized.txt', 'twelve chars');

        $this->assertSame(12, $this->fs->size('sized.txt'));
    }

    public function testLastModified(): void
    {
        $this->fs->write('timed.txt', 'data');

        $timestamp = $this->fs->lastModified('timed.txt');

        $this->assertGreaterThan(0, $timestamp);
        $this->assertLessThanOrEqual(time() + 60, $timestamp);
    }

    public function testMimeType(): void
    {
        $this->fs->write('typed.txt', 'plain text');

        $mime = $this->fs->mimeType('typed.txt');

        $this->assertNotEmpty($mime);
    }

    public function testWriteStreamAndReadStream(): void
    {
        $source = fopen('php://temp', 'r+b');
        fwrite($source, 'stream data');
        rewind($source);

        $this->fs->writeStream('streamed.txt', $source);
        fclose($source);

        $stream = $this->fs->readStream('streamed.txt');

        $this->assertIsResource($stream);
        $this->assertSame('stream data', stream_get_contents($stream));

        fclose($stream);
    }

    public function testListContents(): void
    {
        $this->fs->write('a.txt', 'aaa');
        $this->fs->write('b.txt', 'bbb');
        $this->fs->write('sub/c.txt', 'ccc');

        $items = iterator_to_array($this->fs->listContents(''));
        $paths = array_map(fn (FileInfoInterface $f) => $f->path(), $items);
        sort($paths);

        $this->assertNotEmpty($paths);
        $this->assertContains($this->prefix . 'a.txt', $paths);
        $this->assertContains($this->prefix . 'b.txt', $paths);
    }

    public function testListContentsRecursive(): void
    {
        $this->fs->write('root.txt', 'r');
        $this->fs->write('deep/nested.txt', 'n');

        $items = iterator_to_array($this->fs->listContents('', true));
        $paths = array_map(fn (FileInfoInterface $f) => $f->path(), $items);

        $this->assertContains($this->prefix . 'root.txt', $paths);
        $this->assertContains($this->prefix . 'deep/nested.txt', $paths);
    }

    public function testCreateDirectory(): void
    {
        $this->fs->createDirectory('mydir');

        // S3 directory markers end with / — verify via listContents
        $items = iterator_to_array($this->fs->listContents(''));
        $paths = array_map(fn (FileInfoInterface $f) => $f->path(), $items);

        $this->assertContains($this->prefix . 'mydir/', $paths);
    }

    public function testDeleteDirectory(): void
    {
        $this->fs->write('dir/file1.txt', 'a');
        $this->fs->write('dir/file2.txt', 'b');
        $this->fs->write('dir/sub/file3.txt', 'c');

        $this->fs->deleteDirectory('dir');

        $this->assertFalse($this->fs->exists('dir/file1.txt'));
        $this->assertFalse($this->fs->exists('dir/file2.txt'));
        $this->assertFalse($this->fs->exists('dir/sub/file3.txt'));
    }

    public function testVisibilityPublic(): void
    {
        $this->fs->write('visible.txt', 'data');

        try {
            $this->fs->setVisibility('visible.txt', 'public');
            $this->assertSame('public', $this->fs->getVisibility('visible.txt'));
        } catch (\JardisAdapter\Filesystem\Exception\UnableToWriteException $e) {
            // MinIO does not support ACL-based visibility (HTTP 501)
            $this->assertStringContainsString('501', $e->getMessage());
        }
    }

    public function testVisibilityPrivate(): void
    {
        $this->fs->write('private.txt', 'data');

        try {
            $this->fs->setVisibility('private.txt', 'private');
            $this->assertSame('private', $this->fs->getVisibility('private.txt'));
        } catch (\JardisAdapter\Filesystem\Exception\UnableToWriteException $e) {
            // MinIO does not support ACL-based visibility (HTTP 501)
            $this->assertStringContainsString('501', $e->getMessage());
        }
    }

    public function testPathTraversalPrevented(): void
    {
        $this->expectException(\JardisAdapter\Filesystem\Exception\FilesystemException::class);

        $this->fs->read('../../etc/passwd');
    }

    public function testWriteAndReadLargerContent(): void
    {
        $content = str_repeat('x', 100_000);

        $this->fs->write('large.txt', $content);

        $this->assertSame($content, $this->fs->read('large.txt'));
    }
}
