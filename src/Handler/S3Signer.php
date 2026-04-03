<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler;

use JardisAdapter\Filesystem\Config\S3Config;

/**
 * AWS Signature Version 4 signer for S3-compatible requests.
 */
final class S3Signer
{
    public function __construct(
        private readonly S3Config $config,
    ) {
    }

    /**
     * Signs an S3 request and returns headers including Authorization.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function sign(string $method, string $uri, array $headers, string $payload): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\THis\Z');
        $dateScope = $now->format('Ymd');

        $host = (string) parse_url($this->config->endpoint, PHP_URL_HOST);

        $headers['Host'] = $host;
        $headers['x-amz-date'] = $amzDate;
        $headers['x-amz-content-sha256'] = hash('sha256', $payload);

        // Normalize to lowercase keys and sort for canonical form
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = trim($value);
        }

        ksort($normalized);

        $canonicalHeaders = '';
        $signedHeaderNames = [];

        foreach ($normalized as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
            $signedHeaderNames[] = $name;
        }

        $signedHeadersString = implode(';', $signedHeaderNames);

        $parsedUri = parse_url($uri);
        $canonicalUriPath = $parsedUri['path'] ?? '/';
        $rawQuery = $parsedUri['query'] ?? '';

        // AWS Sig v4: query params must be sorted
        $canonicalQueryString = $this->sortQueryString($rawQuery);

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUriPath,
            $canonicalQueryString,
            $canonicalHeaders,
            $signedHeadersString,
            $normalized['x-amz-content-sha256'],
        ]);

        $scope = implode('/', [$dateScope, $this->config->region, 's3', 'aws4_request']);

        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->deriveSigningKey($dateScope);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        // Rebuild headers map with original casing preserved, add Authorization
        $result = [];

        foreach ($headers as $name => $value) {
            $result[$name] = $value;
        }

        $result['Authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s,SignedHeaders=%s,Signature=%s',
            $this->config->key,
            $scope,
            $signedHeadersString,
            $signature,
        );

        return $result;
    }

    private function sortQueryString(string $query): string
    {
        if ($query === '') {
            return '';
        }

        $pairs = explode('&', $query);
        $sorted = [];

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }

            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $sorted[rawurlencode(rawurldecode($k))] = rawurlencode(rawurldecode($v));
        }

        ksort($sorted);

        $parts = [];

        foreach ($sorted as $k => $v) {
            $parts[] = $k . '=' . $v;
        }

        return implode('&', $parts);
    }

    private function deriveSigningKey(string $dateScope): string
    {
        $kDate = hash_hmac('sha256', $dateScope, 'AWS4' . $this->config->secret, true);
        $kRegion = hash_hmac('sha256', $this->config->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
