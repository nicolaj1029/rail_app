<?php
declare(strict_types=1);

namespace App\Service;

final class TransportOperatorsCatalogLoader
{
    private const CATALOG_PATH = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_operators_catalog.json';

    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];

    /**
     * @return array<string,mixed>
     */
    public static function load(?string $path = null): array
    {
        $path = $path ?? self::CATALOG_PATH;

        if (array_key_exists($path, self::$cache)) {
            return self::$cache[$path];
        }

        if (!is_file($path)) {
            self::$cache[$path] = [];

            return self::$cache[$path];
        }

        $data = json_decode((string)file_get_contents($path), true);
        self::$cache[$path] = is_array($data) ? $data : [];

        return self::$cache[$path];
    }
}
