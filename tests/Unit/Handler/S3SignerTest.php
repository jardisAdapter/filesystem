<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Tests\Unit\Handler;

use JardisAdapter\Filesystem\Config\S3Config;
use JardisAdapter\Filesystem\Handler\S3Signer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisAdapter\Filesystem\Handler\S3Signer
 */
final class S3SignerTest extends TestCase
{
    public function testSignReturnsAuthorizationHeader(): void
    {
        $config = new S3Config(
            bucket: 'test-bucket',
            region: 'us-east-1',
            key: 'AKIAIOSFODNN7EXAMPLE',
            secret: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            endpoint: 'https://s3.amazonaws.com',
        );

        $signer = new S3Signer($config);
        $headers = $signer->sign('GET', '/test-bucket/test.txt', [], '');

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('AWS4-HMAC-SHA256', $headers['Authorization']);
        $this->assertStringContainsString('Credential=AKIAIOSFODNN7EXAMPLE/', $headers['Authorization']);
        $this->assertStringContainsString('SignedHeaders=', $headers['Authorization']);
        $this->assertStringContainsString('Signature=', $headers['Authorization']);
    }

    public function testSignIncludesRequiredHeaders(): void
    {
        $config = new S3Config(
            bucket: 'test-bucket',
            region: 'eu-central-1',
            key: 'AKIATEST',
            secret: 'secretkey',
        );

        $signer = new S3Signer($config);
        $headers = $signer->sign('PUT', '/test-bucket/upload.txt', [], 'body content');

        $this->assertArrayHasKey('Host', $headers);
        $this->assertArrayHasKey('x-amz-date', $headers);
        $this->assertArrayHasKey('x-amz-content-sha256', $headers);
        $this->assertSame('s3.amazonaws.com', $headers['Host']);
        $this->assertSame(hash('sha256', 'body content'), $headers['x-amz-content-sha256']);
    }

    public function testSignPreservesExtraHeaders(): void
    {
        $config = new S3Config(
            bucket: 'bucket',
            region: 'us-east-1',
            key: 'KEY',
            secret: 'SECRET',
        );

        $signer = new S3Signer($config);
        $headers = $signer->sign('PUT', '/bucket/file', ['x-amz-acl' => 'public-read'], '');

        $this->assertSame('public-read', $headers['x-amz-acl']);
        $this->assertStringContainsString('x-amz-acl', $headers['Authorization']);
    }

    public function testSignProducesDeterministicSignatureForSameInput(): void
    {
        $config = new S3Config(
            bucket: 'bucket',
            region: 'us-east-1',
            key: 'KEY',
            secret: 'SECRET',
        );

        $signer = new S3Signer($config);

        $headers1 = $signer->sign('GET', '/bucket/file.txt', [], '');
        $headers2 = $signer->sign('GET', '/bucket/file.txt', [], '');

        $this->assertSame($headers1['Authorization'], $headers2['Authorization']);
    }

    public function testSignUsesCustomEndpointHost(): void
    {
        $config = new S3Config(
            bucket: 'bucket',
            region: 'us-east-1',
            key: 'KEY',
            secret: 'SECRET',
            endpoint: 'https://minio.local:9000',
        );

        $signer = new S3Signer($config);
        $headers = $signer->sign('GET', '/bucket/file.txt', [], '');

        $this->assertSame('minio.local', $headers['Host']);
    }
}
