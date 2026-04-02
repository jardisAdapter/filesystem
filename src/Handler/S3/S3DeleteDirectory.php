<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Config\S3Config;
use JardisAdapter\Filesystem\Exception\UnableToDeleteException;

/**
 * Delete all objects under a prefix in S3-compatible object storage.
 */
final class S3DeleteDirectory
{
    public function __construct(
        private readonly S3Config $config,
        private readonly Closure $sign,
    ) {
    }

    public function __invoke(string $path): void
    {
        $prefix = $this->buildKey($path);

        if ($prefix === '') {
            throw new UnableToDeleteException(
                'Cannot delete directory with empty prefix — would delete entire bucket'
            );
        }

        if (!str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $continuationToken = null;

        do {
            $query = 'list-type=2&prefix=' . rawurlencode($prefix);

            if ($continuationToken !== null) {
                $query .= '&continuation-token=' . rawurlencode($continuationToken);
            }

            $listUrl = rtrim($this->config->endpoint, '/') . '/' . $this->config->bucket . '/?' . $query;
            $canonicalUri = '/' . $this->config->bucket . '/?' . $query;
            $headers = ($this->sign)('GET', $canonicalUri, [], '');

            $responseBody = '';
            $status = 0;
            $curl = curl_init();

            try {
                curl_setopt_array($curl, [
                    CURLOPT_URL => $listUrl,
                    CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);

                $responseBody = (string) curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if (curl_errno($curl) !== 0) {
                    throw new UnableToDeleteException(
                        sprintf('cURL error listing directory "%s": %s', $path, curl_error($curl))
                    );
                }
            } finally {
                curl_close($curl);
            }

            if ($status < 200 || $status >= 300) {
                throw new UnableToDeleteException(
                    sprintf('Unexpected HTTP %d listing directory "%s"', $status, $path)
                );
            }

            $xml = simplexml_load_string($responseBody, 'SimpleXMLElement', LIBXML_NONET);

            if ($xml === false) {
                throw new UnableToDeleteException(
                    sprintf('Unable to parse XML response listing directory "%s"', $path)
                );
            }

            foreach ($xml->Contents as $object) {
                $absoluteKey = (string) $object->Key;
                $this->executeDelete($absoluteKey);
            }

            $isTruncated = strtolower((string) $xml->IsTruncated) === 'true';
            $token = (string) $xml->NextContinuationToken;
            $continuationToken = ($isTruncated && $token !== '') ? $token : null;
        } while ($continuationToken !== null);
    }

    private function executeDelete(string $absoluteKey): void
    {
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $absoluteKey)));
        $url = rtrim($this->config->endpoint, '/') . '/' . $this->config->bucket . '/' . $encodedKey;
        $canonicalUri = '/' . $this->config->bucket . '/' . $absoluteKey;

        $headers = ($this->sign)('DELETE', $canonicalUri, [], '');

        $responseHeaders = [];
        $status = 0;
        $curl = curl_init();

        try {
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$responseHeaders): int {
                    $trimmed = trim($header);
                    if (str_contains($trimmed, ':')) {
                        [$name, $value] = explode(':', $trimmed, 2);
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }

                    return strlen($header);
                },
            ]);

            curl_exec($curl);

            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (curl_errno($curl) !== 0) {
                throw new UnableToDeleteException(
                    sprintf('cURL error deleting "%s": %s', $absoluteKey, curl_error($curl))
                );
            }
        } finally {
            curl_close($curl);
        }

        if ($status < 200 || $status >= 300) {
            throw new UnableToDeleteException(
                sprintf('Unexpected HTTP %d deleting "%s"', $status, $absoluteKey)
            );
        }
    }

    private function buildKey(string $path): string
    {
        $prefix = $this->config->prefix;

        if ($prefix !== '' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        return $prefix . ltrim($path, '/');
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }
}
