<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Handler;

use JardisAdapter\Filesystem\Data\FileInfo;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;
use JardisAdapter\Filesystem\Handler\Local\LocalExists;
use JardisAdapter\Filesystem\Handler\Local\LocalLastModified;
use JardisAdapter\Filesystem\Handler\Local\LocalListContents;
use JardisAdapter\Filesystem\Handler\Local\LocalMimeType;
use JardisAdapter\Filesystem\Handler\Local\LocalRead;
use JardisAdapter\Filesystem\Handler\Local\LocalReadStream;
use JardisAdapter\Filesystem\Handler\Local\LocalSize;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalRead
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalReadStream
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalExists
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalSize
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalLastModified
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalMimeType
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalListContents
 */
final class LocalReaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/jardis_fs_test_' . uniqid();
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testReadReturnsFileContents(): void
    {
        file_put_contents($this->root . '/test.txt', 'hello world');

        $read = new LocalRead($this->root);

        $this->assertSame('hello world', $read('test.txt'));
    }

    public function testReadThrowsFileNotFoundForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalRead($this->root))('missing.txt');
    }

    public function testReadStreamReturnsResource(): void
    {
        file_put_contents($this->root . '/test.txt', 'stream content');

        $stream = (new LocalReadStream($this->root))('test.txt');

        $this->assertIsResource($stream);
        $this->assertSame('stream content', stream_get_contents($stream));

        fclose($stream);
    }

    public function testReadStreamThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalReadStream($this->root))('missing.txt');
    }

    public function testExistsReturnsTrueForExistingFile(): void
    {
        file_put_contents($this->root . '/test.txt', '');

        $this->assertTrue((new LocalExists($this->root, true))('test.txt'));
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse((new LocalExists($this->root, true))('missing.txt'));
    }

    public function testExistsReturnsTrueForDirectory(): void
    {
        mkdir($this->root . '/subdir');

        $this->assertTrue((new LocalExists($this->root, true))('subdir'));
    }

    public function testSizeReturnsFileSize(): void
    {
        file_put_contents($this->root . '/test.txt', 'twelve chars');

        $this->assertSame(12, (new LocalSize($this->root))('test.txt'));
    }

    public function testSizeThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalSize($this->root))('missing.txt');
    }

    public function testLastModifiedReturnsTimestamp(): void
    {
        file_put_contents($this->root . '/test.txt', 'data');

        $timestamp = (new LocalLastModified($this->root))('test.txt');

        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    public function testLastModifiedThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalLastModified($this->root))('missing.txt');
    }

    public function testMimeTypeDetectsTextFile(): void
    {
        file_put_contents($this->root . '/test.txt', 'plain text content');

        $this->assertSame('text/plain', (new LocalMimeType($this->root))('test.txt'));
    }

    public function testMimeTypeThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalMimeType($this->root))('missing.txt');
    }

    public function testListContentsReturnsFileInfoObjects(): void
    {
        file_put_contents($this->root . '/a.txt', 'aaa');
        file_put_contents($this->root . '/b.txt', 'bbb');
        mkdir($this->root . '/sub');

        $list = new LocalListContents($this->root, true);
        $items = iterator_to_array($list('', false));

        $this->assertCount(3, $items);
        $this->assertContainsOnlyInstancesOf(FileInfo::class, $items);

        $paths = array_map(fn (FileInfo $f) => $f->path(), $items);
        sort($paths);

        $this->assertSame(['a.txt', 'b.txt', 'sub'], $paths);
    }

    public function testListContentsRecursive(): void
    {
        file_put_contents($this->root . '/a.txt', 'aaa');
        mkdir($this->root . '/sub');
        file_put_contents($this->root . '/sub/b.txt', 'bbb');

        $list = new LocalListContents($this->root, true);
        $items = iterator_to_array($list('', true));
        $paths = array_map(fn (FileInfo $f) => $f->path(), $items);
        sort($paths);

        $this->assertSame(['a.txt', 'sub', 'sub/b.txt'], $paths);
    }

    public function testListContentsInSubdirectory(): void
    {
        mkdir($this->root . '/sub');
        file_put_contents($this->root . '/sub/file.txt', 'data');

        $list = new LocalListContents($this->root, true);
        $items = iterator_to_array($list('sub', false));

        $this->assertCount(1, $items);
        $this->assertSame('sub/file.txt', $items[0]->path());
        $this->assertTrue($items[0]->isFile());
    }

    public function testListContentsThrowsForMissingDirectory(): void
    {
        $this->expectException(UnableToReadException::class);

        $list = new LocalListContents($this->root, true);
        iterator_to_array($list('missing', false));
    }

    public function testSymlinksIgnoredWhenConfigured(): void
    {
        file_put_contents($this->root . '/real.txt', 'data');
        symlink($this->root . '/real.txt', $this->root . '/link.txt');

        $this->assertFalse((new LocalExists($this->root, false))('link.txt'));
    }

    public function testSymlinksFollowedByDefault(): void
    {
        file_put_contents($this->root . '/real.txt', 'data');
        symlink($this->root . '/real.txt', $this->root . '/link.txt');

        $this->assertTrue((new LocalExists($this->root, true))('link.txt'));
        $this->assertSame('data', (new LocalRead($this->root))('link.txt'));
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
