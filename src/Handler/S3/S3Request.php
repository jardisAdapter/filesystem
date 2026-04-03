<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Config\S3Config;

/**
 * Shared cURL helper for S3 requests.
 */
final class S3Request
{
    private readonly S3BuildKey $buildKey;
    private readonly S3FormatHeaders $formatHeaders;

    public function __construct(
        private readonly S3Config $config,
        private readonly Closure $sign,
    ) {
        $this->buildKey = new S3BuildKey($config);
        $this->formatHeaders = new S3FormatHeaders();
    }

    /**
     * @param array<string, string> $extraHeaders
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function __invoke(
        string $method,
        string $path,
        array $extraHeaders = [],
        string $body = '',
    ): array {
        $key = ($this->buildKey)($path);
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));
        $url = rtrim($this->config->endpoint, '/') . '/' . $this->config->bucket . '/' . $encodedKey;
        $canonicalUri = '/' . $this->config->bucket . '/' . $key;

        $headers = ($this->sign)($method, $canonicalUri, $extraHeaders, $body);

        $responseHeaders = [];
        $responseBody = '';
        $status = 0;
        $curl = curl_init();

        try {
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => ($this->formatHeaders)($headers),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$responseHeaders): int {
                    $trimmed = trim($header);
                    if (str_contains($trimmed, ':')) {
                        [$name, $value] = explode(':', $trimmed, 2);
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }

                    return strlen($header);
                },
            ];

            if ($body !== '') {
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            if ($method === 'HEAD') {
                $options[CURLOPT_NOBODY] = true;
            }

            curl_setopt_array($curl, $options);

            $raw = curl_exec($curl);
            $curlError = ($raw === false) ? curl_error($curl) : null;
            $responseBody = ($raw !== false) ? (string) $raw : '';
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } finally {
            curl_close($curl);
        }

        if ($curlError !== null) {
            return [
                'status' => 0,
                'headers' => [],
                'body' => '',
                'error' => $curlError,
            ];
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $responseBody,
        ];
    }

}
