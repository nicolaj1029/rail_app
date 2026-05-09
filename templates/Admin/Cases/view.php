<?php /** @var \App\Model\Entity\RailCase $case */ ?>
<?php
$formatRemedyChoice = static function (?string $value): string {
    $normalized = trim((string)$value);
    if ($normalized === '') {
        return '-';
    }

    return match ($normalized) {
        'refund_return' => 'Tilbagebetaling',
        'reroute_soonest' => 'Videre rejse hurtigst muligt',
        'reroute_later' => 'Videre rejse senere (efter eget valg)',
        default => $normalized,
    };
};
$snapshot = json_decode((string)($case->flow_snapshot ?? ''), true);
$form = is_array($snapshot) ? (array)($snapshot['form'] ?? []) : [];
$transportMode = strtolower(trim((string)($form['transport_mode'] ?? ($form['gating_mode'] ?? ''))));
$isAirCase = $transportMode === 'air';
$articleLabels = match ($transportMode) {
    'air' => [
        'mode' => 'Air: Art. 7 / 8 / 9',
        'remedy' => 'Afhjaelpning',
        'expenses' => 'Assistanceudgifter',
        'compensation' => 'Kompensation',
    ],
    'rail' => [
        'mode' => 'Rail: Art. 18 / 19 / 20',
        'remedy' => 'Afhjaelpning',
        'expenses' => 'Assistanceudgifter',
        'compensation' => 'Kompensation',
    ],
    'bus' => [
        'mode' => 'Bus: transportspecifikt regelsaet',
        'remedy' => 'Afhjaelpning',
        'expenses' => 'Assistanceudgifter',
        'compensation' => 'Kompensation',
    ],
    'ferry' => [
        'mode' => 'Ferry: Art. 17 / 18 / 19 + PMR',
        'remedy' => 'Art. 18 refund/ombooking / PMR Art. 8(3)',
        'expenses' => 'Art. 17 assistanceudgifter',
        'compensation' => 'Art. 19 kompensation',
    ],
    default => [
        'mode' => 'Transportuafhaengigt admin-resume',
        'remedy' => 'Afhjaelpning',
        'expenses' => 'Assistanceudgifter',
        'compensation' => 'Kompensation',
    ],
};
?>
<h2>Sag <?= h((string)$case->ref) ?></h2>
<div>Status: <strong><?= h((string)$case->status) ?></strong></div>
<div>Rejsedato: <code><?= h($case->travel_date ? $case->travel_date->format('Y-m-d') : '-') ?></code></div>
<div>Passager: <code><?= h((string)$case->passenger_name ?: '-') ?></code></div>
<div>Operator: <code><?= h((string)$case->operator ?: '-') ?></code> (<?= h((string)$case->country ?: '-') ?>)</div>
<div>Delay: <code><?= (int)$case->delay_min_eu ?> min</code></div>
<div>Retsgrundlag: <code><?= h($articleLabels['mode']) ?></code></div>
<div><?= h($articleLabels['remedy']) ?>: <code><?= h($formatRemedyChoice((string)$case->remedy_choice)) ?></code></div>
<div><?= h($articleLabels['expenses']) ?>: <code><?= $case->art20_expenses_total!==null ? h(number_format((float)$case->art20_expenses_total,2,',','.')) : '-' ?></code> <?= h((string)$case->currency) ?></div>
<div><?= h($articleLabels['compensation']) ?>: <code><?= $case->comp_amount!==null ? h(number_format((float)$case->comp_amount,2,',','.')) : '-' ?></code> <?= h((string)$case->currency) ?> <?= $case->comp_band?('(band ' . h((string)$case->comp_band) . '%)') : '' ?></div>
<div>EU-only: <?= $case->eu_only ? 'Ja' : 'Nej' ?></div>
<div>Extraordinary review: <?= $case->extraordinary ? 'Ja' : 'Nej' ?></div>
<hr/>
<h3>Snapshot</h3>
<pre style="max-height:420px;overflow:auto;font-size:11px;"><?= h((string)$case->flow_snapshot) ?></pre>
<p>
  <a href="<?= $this->Url->build(['action' => 'edit', $case->id]) ?>">Rediger admin-sag</a>
  |
  <a href="<?= $this->Url->build(['action' => 'passenger', $case->id]) ?>">Aabn klientsag</a>
  <?php if ($isAirCase): ?>
    |
    <a href="<?= $this->Url->build(['action' => 'airTravelForm', $case->id]) ?>" target="_blank" rel="noopener">Generer air_travel_form.pdf</a>
    |
    <a href="<?= $this->Url->build(['action' => 'airStatementForm', $case->id]) ?>" target="_blank" rel="noopener">Generer staevning-flysag.pdf</a>
  <?php endif; ?>
  |
  <a href="<?= $this->Url->build(['action' => 'index']) ?>">Tilbage</a>
</p>
