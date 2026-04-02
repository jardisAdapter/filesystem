<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Handler;

use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\FilesystemException;
use JardisAdapter\Filesystem\Handler\Local\LocalGetVisibility;
use JardisAdapter\Filesystem\Handler\Local\LocalSetVisibility;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalGetVisibility
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalSetVisibility
 */
final class LocalVisibilityTest extends TestCase
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

    public function testSetVisibilityPublicOnFile(): void
    {
        file_put_contents($this->root . '/test.txt', 'data');
        chmod($this->root . '/test.txt', 0600);

        (new LocalSetVisibility($this->root, 0644, 0600, 0755, 0700))('test.txt', 'public');

        $this->assertSame(0644, fileperms($this->root . '/test.txt') & 0777);
    }

    public function testSetVisibilityPrivateOnFile(): void
    {
        file_put_contents($this->root . '/test.txt', 'data');
        chmod($this->root . '/test.txt', 0644);

        (new LocalSetVisibility($this->root, 0644, 0600, 0755, 0700))('test.txt', 'private');

        $this->assertSame(0600, fileperms($this->root . '/test.txt') & 0777);
    }

    public function testGetVisibilityPublic(): void
    {
        file_put_contents($this->root . '/test.txt', 'data');
        chmod($this->root . '/test.txt', 0644);

        $this->assertSame('public', (new LocalGetVisibility($this->root, 0644, 0755))('test.txt'));
    }

    public function testGetVisibilityPrivate(): void
    {
        file_put_contents($this->root . '/test.txt', 'data');
        chmod($this->root . '/test.txt', 0600);

        $this->assertSame('private', (new LocalGetVisibility($this->root, 0644, 0755))('test.txt'));
    }

    public function testSetVisibilityOnDirectory(): void
    {
        mkdir($this->root . '/mydir', 0700);

        (new LocalSetVisibility($this->root, 0644, 0600, 0755, 0700))('mydir', 'public');

        $this->assertSame(0755, fileperms($this->root . '/mydir') & 0777);
    }

    public function testGetVisibilityOnDirectory(): void
    {
        mkdir($this->root . '/mydir', 0755);

        $this->assertSame('public', (new LocalGetVisibility($this->root, 0644, 0755))('mydir'));
    }

    public function testSetVisibilityPrivateOnDirectory(): void
    {
        mkdir($this->root . '/mydir', 0755);

        (new LocalSetVisibility($this->root, 0644, 0600, 0755, 0700))('mydir', 'private');

        $this->assertSame(0700, fileperms($this->root . '/mydir') & 0777);
    }

    public function testGetVisibilityThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalGetVisibility($this->root, 0644, 0755))('missing.txt');
    }

    public function testSetVisibilityThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new LocalSetVisibility($this->root, 0644, 0600, 0755, 0700))('missing.txt', 'public');
    }

    public function testSetVisibilityThrowsForInvalidVisibility(): void
    {
        file_put_contents($this->root . '/test.txt', 'data');

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('Invalid visibility');

        (new LocalSetVisibility($this->root, 0644, 0600, 0755, 0700))('test.txt', 'invalid');
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
