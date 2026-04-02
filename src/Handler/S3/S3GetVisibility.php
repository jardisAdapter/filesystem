<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Config\S3Config;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Retrieve ACL visibility of a file in S3-compatible object storage.
 */
final class S3GetVisibility
{
    private const VISIBILITY_PUBLIC = 'public';
    private const VISIBILITY_PRIVATE = 'private';
    private const ALL_USERS_URI = 'http://acs.amazonaws.com/groups/global/AllUsers';

    public function __construct(
        private readonly S3Config $config,
        private readonly Closure $sign,
    ) {
    }

    public function __invoke(string $path): string
    {
        $key = $this->buildKey($path);
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));
        $querySuffix = '?acl';
        $canonicalUri = '/' . $this->config->bucket . '/' . $key . $querySuffix;
        $url = rtrim($this->config->endpoint, '/') . '/' . $this->config->bucket . '/' . $encodedKey . $querySuffix;

        $headers = ($this->sign)('GET', $canonicalUri, [], '');

        $responseHeaders = [];
        $responseBody = '';
        $status = 0;
        $curl = curl_init();

        try {
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$responseHeaders): int {
                    $trimmed = trim($header);
                    if (str_contains($trimmed, ':')) {
                        [$name, $value] = explode(':', $trimmed, 2);
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }

                    return strlen($header);
                },
            ]);

            $responseBody = (string) curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (curl_errno($curl) !== 0) {
                throw new UnableToReadException(
                    sprintf('cURL error reading ACL for "%s": %s', $path, curl_error($curl))
                );
            }
        } finally {
            curl_close($curl);
        }

        if ($status === 404) {
            throw new FileNotFoundException(sprintf('File not found: "%s"', $path));
        }

        if ($status < 200 || $status >= 300) {
            throw new UnableToReadException(
                sprintf('Unexpected HTTP %d reading ACL for "%s"', $status, $path)
            );
        }

        $xml = simplexml_load_string($responseBody, 'SimpleXMLElement', LIBXML_NONET);

        if ($xml === false) {
            throw new UnableToReadException(
                sprintf('Unable to parse ACL XML response for: "%s"', $path)
            );
        }

        foreach ($xml->AccessControlList->Grant as $grant) {
            $granteeUri = (string) ($grant->Grantee->URI ?? '');

            if ($granteeUri === self::ALL_USERS_URI) {
                return self::VISIBILITY_PUBLIC;
            }
        }

        return self::VISIBILITY_PRIVATE;
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
