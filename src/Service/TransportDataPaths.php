<?php
declare(strict_types=1);

namespace App\Service;

final class TransportDataPaths
{
    public static function dataDir(): string
    {
        return CONFIG . 'data';
    }

    public static function nodesDir(): string
    {
        return self::dataDir() . DIRECTORY_SEPARATOR . 'nodes';
    }

    public static function nodesSearchDir(): string
    {
        return self::nodesDir() . DIRECTORY_SEPARATOR . 'search';
    }

    public static function nodesSeedsDir(): string
    {
        return self::nodesDir() . DIRECTORY_SEPARATOR . 'seeds';
    }

    public static function stationsCoords(): string
    {
        return self::resolve(
            self::nodesDir() . DIRECTORY_SEPARATOR . 'stations_coords.json',
            self::dataDir() . DIRECTORY_SEPARATOR . 'stations_coords.json'
        );
    }

    public static function transportNodes(): string
    {
        return self::resolve(
            self::nodesDir() . DIRECTORY_SEPARATOR . 'transport_nodes.json',
            self::dataDir() . DIRECTORY_SEPARATOR . 'transport_nodes.json'
        );
    }

    public static function transportNodesSearch(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return self::resolve(
            self::nodesSearchDir() . DIRECTORY_SEPARATOR . $mode . '.json',
            self::dataDir() . DIRECTORY_SEPARATOR . 'transport_nodes_search_' . $mode . '.json'
        );
    }

    public static function busTerminalSeed(): string
    {
        return self::resolve(
            self::nodesSeedsDir() . DIRECTORY_SEPARATOR . 'bus_terminal_seed_v1.json',
            self::dataDir() . DIRECTORY_SEPARATOR . 'bus_terminal_seed_v1.json'
        );
    }

    private static function resolve(string $preferred, string $legacy): string
    {
        if (is_file($preferred)) {
            return $preferred;
        }

        if (is_file($legacy)) {
            return $legacy;
        }

        return $preferred;
    }
}
