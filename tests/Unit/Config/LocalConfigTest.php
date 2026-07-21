<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Config;

use JardisAdapter\Filesystem\Config\LocalConfig;
use JardisAdapter\Filesystem\Exception\FilesystemException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Config\LocalConfig
 */
final class LocalConfigTest extends TestCase
{
    public function testResolvesRootToRealpath(): void
    {
        $root = sys_get_temp_dir();

        $config = new LocalConfig($root);

        $this->assertSame(realpath($root), $config->root);
    }

    public function testThrowsForNonExistentRoot(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('does not exist');

        new LocalConfig('/nonexistent/path/that/does/not/exist');
    }

    public function testThrowsForFileAsRoot(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jardis_test_');

        try {
            $this->expectException(FilesystemException::class);

            new LocalConfig($file);
        } finally {
            @unlink($file);
        }
    }

    public function testDefaultPermissions(): void
    {
        $config = new LocalConfig(sys_get_temp_dir());

        $this->assertSame(0644, $config->filePermissions);
        $this->assertSame(0755, $config->dirPermissions);
        $this->assertTrue($config->followSymlinks);
    }
}
