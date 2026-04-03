<?php

declare(strict_types=1);

namespace JardisAdapter\Filesystem\Handler\S3;

/**
 * Format an associative header map into a list of "Name: Value" strings for cURL.
 */
final class S3FormatHeaders
{
    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    public function __invoke(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }
}
