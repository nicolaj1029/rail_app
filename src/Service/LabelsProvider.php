<?php
declare(strict_types=1);

namespace App\Service;

final class LabelsProvider
{
    /** @var array<string, string[]> */
    private array $labels;

    public function __construct(?string $path = null)
    {
        $path = $path ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . 'labels.json';
        $this->labels = $this->load($path);
    }

    /**
     * @return array<string, string[]>
     */
    private function load(string $path): array
    {
        try {
            if (!is_file($path)) { return []; }
            $json = file_get_contents($path);
            if ($json === false) { return []; }
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Build a regex alternation group from a label key. Escapes all terms safely. */
    public function group(string $key, array $extra = []): string
    {
        $items = $this->labels[$key] ?? [];
        if (!empty($extra)) { $items = array_merge($items, $extra); }
        $items = array_values(array_unique(array_filter(array_map('strval', $items))));
        if (empty($items)) { return '(?!)'; }
        $escaped = array_map(fn(string $s) => preg_quote($s, '/'), $items);
        return '(?:' . implode('|', $escaped) . ')';
    }

    /** Return the raw list of labels for a given key. */
    public function list(string $key): array
    {
        $items = $this->labels[$key] ?? [];
        return array_values(array_unique(array_filter(array_map('strval', $items))));
    }
}
