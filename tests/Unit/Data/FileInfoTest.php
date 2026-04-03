<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Data;

use JardisAdapter\Filesystem\Data\FileInfo;
use JardisSupport\Contract\Filesystem\FileInfoInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Data\FileInfo
 */
final class FileInfoTest extends TestCase
{
    public function testImplementsContract(): void
    {
        $info = new FileInfo('test.txt', 1024, 1700000000, true);

        $this->assertInstanceOf(FileInfoInterface::class, $info);
    }

    public function testFileProperties(): void
    {
        $info = new FileInfo('path/to/file.txt', 2048, 1700000000, true);

        $this->assertSame('path/to/file.txt', $info->path());
        $this->assertSame(2048, $info->size());
        $this->assertSame(1700000000, $info->lastModified());
        $this->assertTrue($info->isFile());
        $this->assertFalse($info->isDirectory());
    }

    public function testDirectoryProperties(): void
    {
        $info = new FileInfo('path/to/dir', 0, 1700000000, false);

        $this->assertFalse($info->isFile());
        $this->assertTrue($info->isDirectory());
        $this->assertSame(0, $info->size());
    }
}
