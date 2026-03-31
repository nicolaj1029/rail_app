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
?>
<h2>Sag <?= h((string)$case->ref) ?></h2>
<div>Status: <strong><?= h((string)$case->status) ?></strong></div>
<div>Rejsedato: <code><?= h($case->travel_date ? $case->travel_date->format('Y-m-d') : '-') ?></code></div>
<div>Passager: <code><?= h((string)$case->passenger_name ?: '-') ?></code></div>
<div>Operatør: <code><?= h((string)$case->operator ?: '-') ?></code> (<?= h((string)$case->country ?: '-') ?>)</div>
<div>Delay: <code><?= (int)$case->delay_min_eu ?> min</code></div>
<div>Art.18 valg: <code><?= h($formatRemedyChoice((string)$case->remedy_choice)) ?></code></div>
<div>Art.20 udgifter: <code><?= $case->art20_expenses_total!==null ? h(number_format((float)$case->art20_expenses_total,2,',','.')) : '-' ?></code> <?= h((string)$case->currency) ?></div>
<div>Art.19 komp: <code><?= $case->comp_amount!==null ? h(number_format((float)$case->comp_amount,2,',','.')) : '-' ?></code> <?= h((string)$case->currency) ?> <?= $case->comp_band?('(band ' . h((string)$case->comp_band) . '%)') : '' ?></div>
<div>EU-only: <?= $case->eu_only ? '✔' : '✖' ?></div>
<div>Extraordinary: <?= $case->extraordinary ? '⚠ Force Majeure' : '–' ?></div>
<hr/>
<h3>Snapshot</h3>
<pre style="max-height:420px;overflow:auto;font-size:11px;"><?= h((string)$case->flow_snapshot) ?></pre>
<p><a href="<?= $this->Url->build(['action' => 'edit', $case->id]) ?>">Rediger</a> · <a href="<?= $this->Url->build(['action' => 'index']) ?>">Tilbage</a></p>
