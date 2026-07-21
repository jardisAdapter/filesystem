<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Handler;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Handler\Local\LocalCopy;
use JardisAdapter\Filesystem\Handler\Local\LocalCreateDirectory;
use JardisAdapter\Filesystem\Handler\Local\LocalDelete;
use JardisAdapter\Filesystem\Handler\Local\LocalDeleteDirectory;
use JardisAdapter\Filesystem\Handler\Local\LocalMove;
use JardisAdapter\Filesystem\Handler\Local\LocalWrite;
use JardisAdapter\Filesystem\Handler\Local\LocalWriteStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalWrite
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalWriteStream
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalDelete
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalCopy
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalMove
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalCreateDirectory
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalDeleteDirectory
 */
final class LocalWriterTest extends TestCase
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

    public function testWriteCreatesFile(): void
    {
        (new LocalWrite($this->root, 0644, 0755))('test.txt', 'hello');

        $this->assertFileExists($this->root . '/test.txt');
        $this->assertSame('hello', file_get_contents($this->root . '/test.txt'));
    }

    public function testWriteCreatesParentDirectories(): void
    {
        (new LocalWrite($this->root, 0644, 0755))('deep/nested/file.txt', 'content');

        $this->assertFileExists($this->root . '/deep/nested/file.txt');
    }

    public function testWriteOverwritesExistingFile(): void
    {
        file_put_contents($this->root . '/test.txt', 'old');

        (new LocalWrite($this->root, 0644, 0755))('test.txt', 'new');

        $this->assertSame('new', file_get_contents($this->root . '/test.txt'));
    }

    public function testWriteStreamFromResource(): void
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, 'stream data');
        rewind($stream);

        (new LocalWriteStream($this->root, 0644, 0755))('stream.txt', $stream);

        fclose($stream);

        $this->assertSame('stream data', file_get_contents($this->root . '/stream.txt'));
    }

    public function testDeleteRemovesFile(): void
    {
        file_put_contents($this->root . '/test.txt', 'data');

        (new LocalDelete($this->root))('test.txt');

        $this->assertFileDoesNotExist($this->root . '/test.txt');
    }

    public function testDeleteThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalDelete($this->root))('missing.txt');
    }

    public function testCopyCopiesFile(): void
    {
        file_put_contents($this->root . '/source.txt', 'original');

        (new LocalCopy($this->root, 0644, 0755))('source.txt', 'target.txt');

        $this->assertSame('original', file_get_contents($this->root . '/source.txt'));
        $this->assertSame('original', file_get_contents($this->root . '/target.txt'));
    }

    public function testCopyThrowsForMissingSource(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalCopy($this->root, 0644, 0755))('missing.txt', 'target.txt');
    }

    public function testCopyCreatesParentDirectories(): void
    {
        file_put_contents($this->root . '/source.txt', 'data');

        (new LocalCopy($this->root, 0644, 0755))('source.txt', 'deep/nested/target.txt');

        $this->assertFileExists($this->root . '/deep/nested/target.txt');
    }

    public function testMoveMovesFile(): void
    {
        file_put_contents($this->root . '/source.txt', 'data');

        (new LocalMove($this->root, 0755))('source.txt', 'target.txt');

        $this->assertFileDoesNotExist($this->root . '/source.txt');
        $this->assertFileExists($this->root . '/target.txt');
        $this->assertSame('data', file_get_contents($this->root . '/target.txt'));
    }

    public function testMoveThrowsForMissingSource(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalMove($this->root, 0755))('missing.txt', 'target.txt');
    }

    public function testCreateDirectoryCreatesDir(): void
    {
        (new LocalCreateDirectory($this->root, 0755))('newdir');

        $this->assertDirectoryExists($this->root . '/newdir');
    }

    public function testCreateDirectoryCreatesNestedDirs(): void
    {
        (new LocalCreateDirectory($this->root, 0755))('a/b/c');

        $this->assertDirectoryExists($this->root . '/a/b/c');
    }

    public function testCreateDirectoryIsIdempotent(): void
    {
        $create = new LocalCreateDirectory($this->root, 0755);
        $create('mydir');
        $create('mydir');

        $this->assertDirectoryExists($this->root . '/mydir');
    }

    public function testDeleteDirectoryRemovesRecursively(): void
    {
        mkdir($this->root . '/dir/sub', 0755, true);
        file_put_contents($this->root . '/dir/file.txt', 'data');
        file_put_contents($this->root . '/dir/sub/nested.txt', 'data');

        (new LocalDeleteDirectory($this->root))('dir');

        $this->assertDirectoryDoesNotExist($this->root . '/dir');
    }

    public function testDeleteDirectoryRemovesEmptyDir(): void
    {
        mkdir($this->root . '/emptydir', 0755);

        (new LocalDeleteDirectory($this->root))('emptydir');

        $this->assertDirectoryDoesNotExist($this->root . '/emptydir');
    }

    public function testDeleteDirectoryThrowsForMissingDir(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalDeleteDirectory($this->root))('missing');
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
