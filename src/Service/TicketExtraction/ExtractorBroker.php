<?php
declare(strict_types=1);

namespace App\Service\TicketExtraction;

final class ExtractorBroker
{
    /** @var ExtractorInterface[] */
    private array $providers;
    private float $threshold;
    private bool $alwaysMerge;
    /** @var string[] */
    private array $coreKeys = ['dep_station','arr_station','dep_date','dep_time','arr_time','train_no'];

    /**
     * @param ExtractorInterface[] $providers in priority order
     */
    public function __construct(array $providers, float $threshold = 0.66, bool $alwaysMerge = false)
    {
        $this->providers = $providers;
        $this->threshold = $threshold;
        $this->alwaysMerge = $alwaysMerge;
    }

    public function run(string $text): TicketExtractionResult
    {
        if (empty($this->providers)) {
            return new TicketExtractionResult([], 0.0, 'none', ['No providers available']);
        }

        // 1) Always run the first provider (typically heuristics)
        $first = $this->providers[0]->extract($text);
        $mergedFields = $first->fields;
        $logs = $first->logs;
        $provider = $first->provider;
        $mergedFromLater = false;

        $firstComplete = $this->allCorePresent($mergedFields);
        if (!$this->alwaysMerge && $first->confidence >= $this->threshold && $firstComplete) {
            return $first; // Fast path: confident and complete enough
        }

        // 2) Try subsequent providers to either reach threshold, or fill missing core fields
        $best = $first;
        for ($i = 1; $i < count($this->providers); $i++) {
            $r = $this->providers[$i]->extract($text);
            $logs = array_merge($logs, $r->logs);

            // Merge only missing/empty fields from this provider
            foreach ($r->fields as $k => $v) {
                if ((!isset($mergedFields[$k]) || $mergedFields[$k] === null || $mergedFields[$k] === '')
                    && $v !== null && $v !== '') {
                    $mergedFields[$k] = $v;
                    $mergedFromLater = true;
                }
            }

            // Track best single-provider result too
            if ($r->confidence > $best->confidence) { $best = $r; }

            // If after merging we have all core fields, return a hybrid
            if (!$this->alwaysMerge && $this->allCorePresent($mergedFields)) {
                $conf = $this->confidenceFromFields($mergedFields);
                $hybridProvider = ($provider === $r->provider) ? $provider : 'hybrid';
                return new TicketExtractionResult($mergedFields, $conf, $hybridProvider, $logs);
            }

            // Or if this provider meets threshold alone, return it
            if (!$this->alwaysMerge && $r->confidence >= $this->threshold) { return $r; }
        }

        // 3) Nothing complete; prefer merged if it improved coverage/confidence
        $mergedConf = $this->confidenceFromFields($mergedFields);
        if ($this->alwaysMerge) {
            $mergedProvider = $mergedFromLater ? 'hybrid' : $provider;
            return new TicketExtractionResult($mergedFields, $mergedConf, $mergedProvider, $logs);
        }
        if ($mergedConf > $best->confidence) {
            return new TicketExtractionResult($mergedFields, $mergedConf, 'hybrid', $logs);
        }
        return $best;
    }

    /**
     * @param array<string,?string> $fields
     */
    private function allCorePresent(array $fields): bool
    {
        foreach ($this->coreKeys as $k) {
            $v = $fields[$k] ?? null;
            if (!is_string($v) || $v === '') { return false; }
        }
        return true;
    }

    /**
     * @param array<string,?string> $fields
     */
    private function confidenceFromFields(array $fields): float
    {
        $score = 0; $max = count($this->coreKeys);
        foreach ($this->coreKeys as $k) {
            $v = $fields[$k] ?? null;
            if (is_string($v) && $v !== '') { $score++; }
        }
        return $max > 0 ? $score / $max : 0.0;
    }
}
