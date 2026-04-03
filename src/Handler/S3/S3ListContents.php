<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Config\S3Config;
use JardisAdapter\Filesystem\Data\FileInfo;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * List directory contents in S3-compatible object storage with pagination support.
 */
final class S3ListContents
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
     * @return iterable<FileInfo>
     */
    public function __invoke(string $path, bool $recursive): iterable
    {
        $prefix = ($this->buildKey)($path);

        if ($prefix !== '' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $continuationToken = null;

        do {
            $query = 'list-type=2';
            $query .= '&prefix=' . rawurlencode($prefix);

            if (!$recursive) {
                $query .= '&delimiter=' . rawurlencode('/');
            }

            if ($continuationToken !== null) {
                $query .= '&continuation-token=' . rawurlencode($continuationToken);
            }

            $canonicalUri = '/' . $this->config->bucket . '/?' . $query;
            $fullUrl = rtrim($this->config->endpoint, '/') . '/' . $this->config->bucket . '/?' . $query;

            $headers = ($this->sign)('GET', $canonicalUri, [], '');

            $responseBody = '';
            $status = 0;
            $curl = curl_init();

            try {
                curl_setopt_array($curl, [
                    CURLOPT_URL => $fullUrl,
                    CURLOPT_HTTPHEADER => ($this->formatHeaders)($headers),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);

                $responseBody = (string) curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if (curl_errno($curl) !== 0) {
                    throw new UnableToReadException(
                        sprintf('cURL error listing "%s": %s', $path, curl_error($curl))
                    );
                }
            } finally {
                curl_close($curl);
            }

            if ($status < 200 || $status >= 300) {
                throw new UnableToReadException(
                    sprintf('Unexpected HTTP %d listing "%s"', $status, $path)
                );
            }

            $xml = simplexml_load_string($responseBody, 'SimpleXMLElement', LIBXML_NONET);

            if ($xml === false) {
                throw new UnableToReadException(
                    sprintf('Unable to parse XML response for listing "%s"', $path)
                );
            }

            foreach ($xml->Contents as $object) {
                $key = (string) $object->Key;

                if (str_ends_with($key, '/')) {
                    yield new FileInfo(
                        path: $key,
                        size: 0,
                        lastModified: 0,
                        isFile: false,
                    );
                    continue;
                }

                $lastModifiedRaw = (string) $object->LastModified;
                $lastModifiedTs = strtotime($lastModifiedRaw);

                yield new FileInfo(
                    path: $key,
                    size: (int) $object->Size,
                    lastModified: $lastModifiedTs !== false ? $lastModifiedTs : 0,
                    isFile: true,
                );
            }

            foreach ($xml->CommonPrefixes as $prefixEntry) {
                yield new FileInfo(
                    path: (string) $prefixEntry->Prefix,
                    size: 0,
                    lastModified: 0,
                    isFile: false,
                );
            }

            $isTruncated = strtolower((string) $xml->IsTruncated) === 'true';
            $token = (string) $xml->NextContinuationToken;
            $continuationToken = ($isTruncated && $token !== '') ? $token : null;
        } while ($continuationToken !== null);
    }

}
