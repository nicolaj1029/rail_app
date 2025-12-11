<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Utility\Text;

class FixtureRepository
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = $baseDir ?? (ROOT . DS . 'tests' . DS . 'fixtures' . DS . 'demo' . DS);
    }

    /**
     * Load all fixtures (or a single id).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(?string $id = null): array
    {
        if (!is_dir($this->baseDir)) {
            return [];
        }
        $out = [];
        foreach (glob($this->baseDir . '*.json') as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }
            $fixture = json_decode($json, true);
            if (!is_array($fixture)) {
                continue;
            }
            // Allow wrapped format { "fixture": { ... } }
            if (isset($fixture['fixture']) && is_array($fixture['fixture']) && !isset($fixture['fixture']['fixture'])) {
                $fixture = $fixture['fixture'];
            }
            if (empty($fixture['id'])) {
                $fixture['id'] = Text::slug(basename($file, '.json'));
            }
            if ($id !== null && $fixture['id'] !== $id) {
                continue;
            }
            $out[] = $fixture;
        }
        return $out;
    }

    /**
     * Lightweight meta listing.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listMeta(): array
    {
        $meta = [];
        foreach ($this->getAll() as $fx) {
            $meta[] = [
                'id' => $fx['id'],
                'version' => $fx['version'] ?? 1,
                'label' => $fx['label'] ?? $fx['id'],
                'tags' => $fx['tags'] ?? [],
            ];
        }
        return $meta;
    }
}
