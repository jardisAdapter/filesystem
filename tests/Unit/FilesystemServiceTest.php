<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit;

use JardisAdapter\Filesystem\Config\LocalConfig;
use JardisAdapter\Filesystem\FilesystemService;
use JardisSupport\Contract\Filesystem\FilesystemInterface;
use JardisSupport\Contract\Filesystem\FilesystemServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\FilesystemService
 */
final class FilesystemServiceTest extends TestCase
{
    public function testImplementsServiceInterface(): void
    {
        $this->assertInstanceOf(FilesystemServiceInterface::class, new FilesystemService());
    }

    public function testLocalReturnsFilesystemInterface(): void
    {
        $fs = (new FilesystemService())->local(sys_get_temp_dir());

        $this->assertInstanceOf(FilesystemInterface::class, $fs);
    }

    public function testLocalReturnsSeparateInstances(): void
    {
        $service = new FilesystemService();

        $fs1 = $service->local(sys_get_temp_dir());
        $fs2 = $service->local(sys_get_temp_dir());

        $this->assertNotSame($fs1, $fs2);
    }

    public function testCreateReturnsFilesystemInterface(): void
    {
        $fs = (new FilesystemService())->create(new LocalConfig(sys_get_temp_dir()));

        $this->assertInstanceOf(FilesystemInterface::class, $fs);
    }

    public function testCreateWithCustomConfig(): void
    {
        $config = new LocalConfig(
            sys_get_temp_dir(),
            filePermissions: 0600,
            dirPermissions: 0700,
        );

        $fs = (new FilesystemService())->create($config);

        $this->assertInstanceOf(FilesystemInterface::class, $fs);
    }
}
