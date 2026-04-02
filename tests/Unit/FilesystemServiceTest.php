<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit;

use JardisAdapter\Filesystem\Config\LocalConfig;
use JardisAdapter\Filesystem\FilesystemService;
use JardisSupport\Contract\Filesystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\FilesystemService
 */
final class FilesystemServiceTest extends TestCase
{
    public function testCreateReturnsFilesystemInterface(): void
    {
        $service = new FilesystemService();

        $fs = $service->create(new LocalConfig(sys_get_temp_dir()));

        $this->assertInstanceOf(FilesystemInterface::class, $fs);
    }

    public function testCreateReturnsSeparateInstances(): void
    {
        $service = new FilesystemService();
        $config = new LocalConfig(sys_get_temp_dir());

        $fs1 = $service->create($config);
        $fs2 = $service->create($config);

        $this->assertNotSame($fs1, $fs2);
    }
}
