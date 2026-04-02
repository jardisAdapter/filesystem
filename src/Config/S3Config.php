<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Config;

/**
 * Configuration for the S3-compatible storage adapter.
 */
final readonly class S3Config
{
    public function __construct(
        public string $bucket,
        public string $region,
        public string $key,
        #[\SensitiveParameter]
        public string $secret,
        public string $endpoint = 'https://s3.amazonaws.com',
        public string $prefix = '',
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'bucket' => $this->bucket,
            'region' => $this->region,
            'key' => $this->key,
            'secret' => '********',
            'endpoint' => $this->endpoint,
            'prefix' => $this->prefix,
        ];
    }
}
