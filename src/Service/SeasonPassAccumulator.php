<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Minimal accumulator for Season/Period pass incidents to support Art. 19(2).
 * Stores entries in session namespace 'flow.season_incidents'.
 * Entry shape: { date: YYYY-MM-DD, minutes: int, cancelled: bool, operator?: string }
 */
class SeasonPassAccumulator
{
    /** @var \Cake\Http\Session|null */
    private $session;

    public function __construct(?\Cake\Http\Session $session)
    {
        $this->session = $session;
    }

    /** Append an incident row (idempotent-ish by date+minutes+cancelled hash) */
    public function addIncident(array $row): void
    {
        if (!$this->session) { return; }
        $list = (array)$this->session->read('flow.season_incidents') ?: [];
        // Normalize
        $date = (string)($row['date'] ?? date('Y-m-d'));
        $minutes = max(0, (int)($row['minutes'] ?? 0));
        $cancelled = (bool)($row['cancelled'] ?? false);
        $operator = isset($row['operator']) ? (string)$row['operator'] : null;
        $key = $date . '|' . $minutes . '|' . ($cancelled ? '1' : '0') . '|' . ($operator ?? '');
        // Avoid duplicates in same session
        $exists = false;
        foreach ($list as $it) {
            $k2 = (string)($it['date'] ?? '') . '|' . (int)($it['minutes'] ?? 0) . '|' . (!empty($it['cancelled']) ? '1' : '0') . '|' . (string)($it['operator'] ?? '');
            if ($k2 === $key) { $exists = true; break; }
        }
        if (!$exists) {
            $list[] = [ 'date' => $date, 'minutes' => $minutes, 'cancelled' => $cancelled, 'operator' => $operator ];
            $this->session->write('flow.season_incidents', $list);
        }
    }

    /** Return raw list */
    public function all(): array
    {
        if (!$this->session) { return []; }
        return (array)$this->session->read('flow.season_incidents') ?: [];
    }

    /**
     * Summarize incidents for UI: counts and cumulated minutes below 60.
     * @return array{
     *   count_total:int, count_cancel:int, count_ge60:int, count_20_59:int, count_lt20:int,
     *   cum_minutes_lt60:int, examples?:array<int,array<string,mixed>>
     * }
     */
    public function summarize(array $rows = []): array
    {
        if (empty($rows)) { $rows = $this->all(); }
        $total = 0; $cancel = 0; $ge60 = 0; $b20_59 = 0; $lt20 = 0; $cumLt60 = 0;
        $examples = [];
        foreach ($rows as $r) {
            $total++;
            $min = max(0, (int)($r['minutes'] ?? 0));
            $isCanc = !empty($r['cancelled']); if ($isCanc) { $cancel++; }
            if ($min >= 60) { $ge60++; }
            elseif ($min >= 20) { $b20_59++; $cumLt60 += $min; }
            else { $lt20++; $cumLt60 += $min; }
            if (count($examples) < 5) { $examples[] = $r; }
        }
        return [
            'count_total' => $total,
            'count_cancel' => $cancel,
            'count_ge60' => $ge60,
            'count_20_59' => $b20_59,
            'count_lt20' => $lt20,
            'cum_minutes_lt60' => $cumLt60,
            'examples' => $examples,
        ];
    }
}

?>
