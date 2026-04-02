<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Handler;

use JardisAdapter\Filesystem\Exception\FilesystemException;
use JardisAdapter\Filesystem\Handler\Local\LocalFullPath;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Handler\Local\LocalFullPath
 */
final class LocalFullPathTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/jardis_fp_test_' . uniqid();
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testReturnsFullPathForNormalFile(): void
    {
        file_put_contents($this->root . '/test.txt', 'data');

        $resolve = new LocalFullPath(realpath($this->root));
        $result = $resolve('test.txt');

        $this->assertSame(realpath($this->root) . '/test.txt', $result);
    }

    public function testAllowsNonExistentPaths(): void
    {
        $resolve = new LocalFullPath(realpath($this->root));
        $result = $resolve('future-file.txt');

        $this->assertSame(realpath($this->root) . '/future-file.txt', $result);
    }

    public function testThrowsForSymlinkEscape(): void
    {
        $outsideDir = sys_get_temp_dir() . '/jardis_outside_' . uniqid();
        mkdir($outsideDir, 0755, true);
        file_put_contents($outsideDir . '/secret.txt', 'secret');

        symlink($outsideDir, $this->root . '/escape');

        $resolve = new LocalFullPath(realpath($this->root));

        try {
            $this->expectException(FilesystemException::class);
            $this->expectExceptionMessage('escapes storage root');

            $resolve('escape/secret.txt');
        } finally {
            $this->removeDir($outsideDir);
        }
    }

    public function testAllowsSymlinksWithinRoot(): void
    {
        mkdir($this->root . '/target', 0755);
        file_put_contents($this->root . '/target/file.txt', 'data');
        symlink($this->root . '/target', $this->root . '/link');

        $resolve = new LocalFullPath(realpath($this->root));
        $result = $resolve('link/file.txt');

        $this->assertSame(realpath($this->root) . '/link/file.txt', $result);
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
