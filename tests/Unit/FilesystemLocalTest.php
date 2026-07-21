<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit;

use JardisAdapter\Filesystem\Config\LocalConfig;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\FilesystemException;
use JardisAdapter\Filesystem\Filesystem;
use JardisSupport\Contract\Filesystem\FileInfoInterface;
use JardisSupport\Contract\Filesystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Filesystem
 */
final class FilesystemLocalTest extends TestCase
{
    private string $root;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/jardis_fs_test_' . uniqid();
        mkdir($this->root, 0755, true);

        $this->fs = new Filesystem(new LocalConfig($this->root));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testImplementsFilesystemInterface(): void
    {
        $this->assertInstanceOf(FilesystemInterface::class, $this->fs);
    }

    public function testWriteAndRead(): void
    {
        $this->fs->write('test.txt', 'hello world');

        $this->assertSame('hello world', $this->fs->read('test.txt'));
    }

    public function testWriteCreatesDirectories(): void
    {
        $this->fs->write('deep/nested/file.txt', 'content');

        $this->assertSame('content', $this->fs->read('deep/nested/file.txt'));
    }

    public function testExists(): void
    {
        $this->assertFalse($this->fs->exists('test.txt'));

        $this->fs->write('test.txt', 'data');

        $this->assertTrue($this->fs->exists('test.txt'));
    }

    public function testDelete(): void
    {
        $this->fs->write('test.txt', 'data');
        $this->fs->delete('test.txt');

        $this->assertFalse($this->fs->exists('test.txt'));
    }

    public function testDeleteThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->fs->delete('missing.txt');
    }

    public function testCopy(): void
    {
        $this->fs->write('source.txt', 'data');
        $this->fs->copy('source.txt', 'target.txt');

        $this->assertSame('data', $this->fs->read('source.txt'));
        $this->assertSame('data', $this->fs->read('target.txt'));
    }

    public function testMove(): void
    {
        $this->fs->write('source.txt', 'data');
        $this->fs->move('source.txt', 'target.txt');

        $this->assertFalse($this->fs->exists('source.txt'));
        $this->assertSame('data', $this->fs->read('target.txt'));
    }

    public function testSize(): void
    {
        $this->fs->write('test.txt', 'twelve chars');

        $this->assertSame(12, $this->fs->size('test.txt'));
    }

    public function testLastModified(): void
    {
        $this->fs->write('test.txt', 'data');

        $timestamp = $this->fs->lastModified('test.txt');

        $this->assertGreaterThan(0, $timestamp);
        $this->assertLessThanOrEqual(time(), $timestamp);
    }

    public function testMimeType(): void
    {
        $this->fs->write('test.txt', 'plain text');

        $this->assertSame('text/plain', $this->fs->mimeType('test.txt'));
    }

    public function testWriteStreamAndReadStream(): void
    {
        $source = fopen('php://temp', 'r+b');
        fwrite($source, 'stream data');
        rewind($source);

        $this->fs->writeStream('stream.txt', $source);
        fclose($source);

        $stream = $this->fs->readStream('stream.txt');

        $this->assertIsResource($stream);
        $this->assertSame('stream data', stream_get_contents($stream));

        fclose($stream);
    }

    public function testListContents(): void
    {
        $this->fs->write('a.txt', 'aaa');
        $this->fs->write('b.txt', 'bbb');
        $this->fs->createDirectory('subdir');

        $items = iterator_to_array($this->fs->listContents(''));

        $this->assertCount(3, $items);
        $this->assertContainsOnlyInstancesOf(FileInfoInterface::class, $items);
    }

    public function testListContentsRecursive(): void
    {
        $this->fs->write('a.txt', 'aaa');
        $this->fs->write('sub/b.txt', 'bbb');

        $items = iterator_to_array($this->fs->listContents('', true));
        $paths = array_map(fn (FileInfoInterface $f) => $f->path(), $items);
        sort($paths);

        $this->assertSame(['a.txt', 'sub', 'sub/b.txt'], $paths);
    }

    public function testCreateDirectory(): void
    {
        $this->fs->createDirectory('mydir');

        $this->assertTrue($this->fs->exists('mydir'));
    }

    public function testDeleteDirectory(): void
    {
        $this->fs->write('dir/file.txt', 'data');
        $this->fs->deleteDirectory('dir');

        $this->assertFalse($this->fs->exists('dir'));
    }

    public function testVisibility(): void
    {
        $this->fs->write('test.txt', 'data');

        $this->fs->setVisibility('test.txt', 'private');
        $this->assertSame('private', $this->fs->getVisibility('test.txt'));

        $this->fs->setVisibility('test.txt', 'public');
        $this->assertSame('public', $this->fs->getVisibility('test.txt'));
    }

    public function testPathTraversalPrevented(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('Path traversal detected');

        $this->fs->read('../../../etc/passwd');
    }

    public function testPathNormalizationStripLeadingSlash(): void
    {
        $this->fs->write('test.txt', 'data');

        $this->assertSame('data', $this->fs->read('/test.txt'));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
