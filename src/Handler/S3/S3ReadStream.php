<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

use Closure;
use JardisAdapter\Filesystem\Config\S3Config;
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisAdapter\Filesystem\Exception\UnableToReadException;

/**
 * Stream file contents from S3-compatible object storage via CURLOPT_FILE.
 */
final class S3ReadStream
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
     * @return resource
     */
    public function __invoke(string $path)
    {
        $url = $this->buildUrl($path);
        $key = ($this->buildKey)($path);
        $canonicalUri = '/' . $this->config->bucket . '/' . $key;

        $headers = ($this->sign)('GET', $canonicalUri, [], '');

        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            throw new UnableToReadException(
                sprintf('Unable to open temp stream for: "%s"', $path)
            );
        }

        $status = 0;
        $curl = curl_init();

        try {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ($this->formatHeaders)($headers));
            curl_setopt($curl, CURLOPT_FILE, $stream);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);

            $responseHeaders = [];
            curl_setopt(
                $curl,
                CURLOPT_HEADERFUNCTION,
                static function ($ch, string $header) use (&$responseHeaders): int {
                    $trimmed = trim($header);
                    if (str_contains($trimmed, ':')) {
                        [$name, $value] = explode(':', $trimmed, 2);
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }

                    return strlen($header);
                }
            );

            curl_exec($curl);

            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (curl_errno($curl) !== 0) {
                throw new UnableToReadException(
                    sprintf('cURL error reading "%s": %s', $path, curl_error($curl))
                );
            }
        } finally {
            curl_close($curl);
        }

        if ($status === 404) {
            fclose($stream);
            throw new FileNotFoundException(sprintf('File not found: "%s"', $path));
        }

        if ($status < 200 || $status >= 300) {
            fclose($stream);
            throw new UnableToReadException(
                sprintf('Unexpected HTTP %d reading "%s"', $status, $path)
            );
        }

        rewind($stream);

        return $stream;
    }

    /**
     * @return non-empty-string
     */
    private function buildUrl(string $path): string
    {
        $key = ($this->buildKey)($path);
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));

        return rtrim($this->config->endpoint, '/') . '/' . $this->config->bucket . '/' . $encodedKey;
    }
}
