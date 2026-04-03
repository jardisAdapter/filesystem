<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Config;

use JardisAdapter\Filesystem\Config\S3Config;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Config\S3Config
 */
final class S3ConfigTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $config = new S3Config(
            bucket: 'my-bucket',
            region: 'eu-central-1',
            key: 'AKIAEXAMPLE',
            secret: 'supersecret',
            endpoint: 'https://s3.example.com',
            prefix: 'uploads/',
        );

        $this->assertSame('my-bucket', $config->bucket);
        $this->assertSame('eu-central-1', $config->region);
        $this->assertSame('AKIAEXAMPLE', $config->key);
        $this->assertSame('supersecret', $config->secret);
        $this->assertSame('https://s3.example.com', $config->endpoint);
        $this->assertSame('uploads/', $config->prefix);
    }

    public function testDefaultValues(): void
    {
        $config = new S3Config(
            bucket: 'bucket',
            region: 'us-east-1',
            key: 'key',
            secret: 'secret',
        );

        $this->assertSame('https://s3.amazonaws.com', $config->endpoint);
        $this->assertSame('', $config->prefix);
    }

    public function testDebugInfoMasksSecret(): void
    {
        $config = new S3Config(
            bucket: 'bucket',
            region: 'us-east-1',
            key: 'AKIATEST',
            secret: 'my-super-secret-key',
        );

        $debug = $config->__debugInfo();

        $this->assertSame('********', $debug['secret']);
        $this->assertSame('AKIATEST', $debug['key']);
        $this->assertSame('bucket', $debug['bucket']);
    }
}
