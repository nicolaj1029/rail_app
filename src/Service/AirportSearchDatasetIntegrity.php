<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class AirportSearchDatasetIntegrity
{
    public const MIN_AIRPORT_COUNT = 5000;
    public const MIN_INDEX_BYTES = 1000000;

    /** @var array<int,string> */
    public const SENTINEL_CODES = ['CPH', 'ARN', 'BRU', 'LHR', 'CDG'];

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    public static function errors(array $rows): array
    {
        $errors = [];
        if (count($rows) < self::MIN_AIRPORT_COUNT) {
            $errors[] = sprintf(
                'airport count %d is below the minimum %d',
                count($rows),
                self::MIN_AIRPORT_COUNT
            );
        }

        $codeCounts = array_fill_keys(self::SENTINEL_CODES, 0);
        foreach ($rows as $row) {
            $code = strtoupper(trim((string)($row['iata_code'] ?? $row['code'] ?? '')));
            if (array_key_exists($code, $codeCounts)) {
                $codeCounts[$code]++;
            }
        }

        foreach ($codeCounts as $code => $count) {
            if ($count === 0) {
                $errors[] = 'required sentinel airport is missing: ' . $code;
            } elseif ($count > 1) {
                $errors[] = sprintf('required sentinel airport is duplicated: %s (%d rows)', $code, $count);
            }
        }

        return $errors;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public static function assertValid(array $rows, string $context = 'airport search dataset'): void
    {
        $errors = self::errors($rows);
        if ($errors !== []) {
            throw new RuntimeException($context . ' failed integrity validation: ' . implode('; ', $errors));
        }
    }

    public static function preflightFile(string $path): ?string
    {
        if (!is_file($path)) {
            return 'airport search index is missing: ' . $path;
        }

        $size = @filesize($path);
        if (!is_int($size) || $size < self::MIN_INDEX_BYTES) {
            return sprintf(
                'airport search index is empty or truncated: %s (%d bytes, minimum %d)',
                $path,
                is_int($size) ? $size : 0,
                self::MIN_INDEX_BYTES
            );
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return 'airport search index is unreadable: ' . $path;
        }

        try {
            $first = (string)fread($handle, 512);
            if (@fseek($handle, max(0, $size - 512)) !== 0) {
                return 'airport search index could not be inspected: ' . $path;
            }
            $last = (string)fread($handle, 512);
        } finally {
            fclose($handle);
        }

        if (!str_starts_with(ltrim($first), '[') || !str_ends_with(rtrim($last), ']')) {
            return 'airport search index is not a complete JSON list: ' . $path;
        }

        return null;
    }
}
