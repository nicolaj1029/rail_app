<?php
declare(strict_types=1);

namespace App\Service;

final class CodexSourcingPlan
{
    private const PLAN_PATH = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'codex_sourcing_plan.json';

    /** @var array<string,mixed> */
    private array $data = [];

    public function __construct(?string $path = null)
    {
        $path = $path ?? self::PLAN_PATH;
        if (!is_file($path)) {
            $this->data = [
                'source' => null,
                'generated_at' => null,
                'format' => 'json',
                'tabs' => [],
            ];
            return;
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        $this->data = is_array($decoded) ? $decoded : [
            'source' => null,
            'generated_at' => null,
            'format' => 'json',
            'tabs' => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function raw(): array
    {
        return $this->data;
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $tabs = $this->tabs();
        $summary = [];

        foreach ($tabs as $name => $tab) {
            $tasks = $tab['tasks'] ?? [];
            $summary[$name] = [
                'goal' => (string)($tab['goal'] ?? ''),
                'priority_order' => array_values((array)($tab['priority_order'] ?? [])),
                'task_count' => is_array($tasks) ? count($tasks) : 0,
                'high_priority_tasks' => $this->countTasksByPriority($tasks, 'high'),
                'medium_priority_tasks' => $this->countTasksByPriority($tasks, 'medium'),
            ];
        }

        return [
            'source' => (string)($this->data['source'] ?? ''),
            'generated_at' => (string)($this->data['generated_at'] ?? ''),
            'format' => (string)($this->data['format'] ?? 'json'),
            'tabs' => $summary,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function tabs(): array
    {
        $tabs = $this->data['tabs'] ?? [];
        return is_array($tabs) ? $tabs : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function tasks(string $tab): array
    {
        $tabs = $this->tabs();
        $tab = strtolower(trim($tab));
        if ($tab === '') {
            return [];
        }

        $key = $this->resolveTabKey($tabs, $tab);
        if ($key === null) {
            return [];
        }

        $tasks = $tabs[$key]['tasks'] ?? [];
        return is_array($tasks) ? array_values($tasks) : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function tab(string $tab): ?array
    {
        $tabs = $this->tabs();
        $tab = strtolower(trim($tab));
        if ($tab === '') {
            return null;
        }

        $key = $this->resolveTabKey($tabs, $tab);
        if ($key === null) {
            return null;
        }

        $value = $tabs[$key];
        return is_array($value) ? $value : null;
    }

    /**
     * @param array<int,array<string,mixed>> $tasks
     */
    private function countTasksByPriority(array $tasks, string $priority): int
    {
        $count = 0;
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            if (strtolower((string)($task['priority'] ?? '')) === $priority) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string,array<string,mixed>> $tabs
     */
    private function resolveTabKey(array $tabs, string $needle): ?string
    {
        foreach (array_keys($tabs) as $key) {
            if (strtolower((string)$key) === $needle) {
                return (string)$key;
            }
        }

        return null;
    }
}
