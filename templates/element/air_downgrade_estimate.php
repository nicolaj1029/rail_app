<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$airScope = (array)($airScope ?? []);
$airRights = (array)($airRights ?? []);
$airContract = (array)($airContract ?? []);

$distanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($airScope['air_distance_band'] ?? ($airRights['air_distance_band'] ?? '')))));
$distanceBandLabels = [
    'up_to_1500' => '1500 km eller mindre',
    'intra_eu_over_1500' => 'Inden for EU over 1500 km',
    'other_1500_to_3500' => 'Andre flyvninger 1500-3500 km',
    'other_over_3500' => 'Andre flyvninger over 3500 km',
];
$classLabels = [
    'economy' => 'Economy',
    'premium_economy' => 'Premium Economy',
    'business' => 'Business',
    'first' => 'First',
];
$distanceBandLabel = $distanceBandLabels[$distanceBand] ?? 'Afventer distancekategori';

$autoRefundPercent = match ($distanceBand) {
    'up_to_1500' => '30',
    'intra_eu_over_1500', 'other_1500_to_3500' => '50',
    'other_over_3500' => '75',
    default => '',
};
$bookedClass = strtolower(trim((string)($form['air_downgrade_booked_class'] ?? '')));
$flownClass = strtolower(trim((string)($form['air_downgrade_flown_class'] ?? '')));
$selectedRefundPercent = (string)($form['air_downgrade_refund_percent'] ?? ($autoRefundPercent !== '' ? $autoRefundPercent : ''));
$selectedRatePercent = is_numeric($selectedRefundPercent) ? (float)$selectedRefundPercent : 0.0;
$baseTicketCurrency = strtoupper(trim((string)($form['price_currency'] ?? ($meta['_auto']['price_currency']['value'] ?? 'EUR'))));
if ($baseTicketCurrency === '' || $baseTicketCurrency === 'AUTO') {
    $baseTicketCurrency = 'EUR';
}
$baseTicketPrice = trim((string)($form['price'] ?? ($meta['_auto']['price']['value'] ?? '')));
$ticketPriceKnown = strtolower((string)($form['air_downgrade_ticket_price_known'] ?? ($baseTicketPrice !== '' ? 'yes' : 'no')));
if (!in_array($ticketPriceKnown, ['yes', 'no'], true)) {
    $ticketPriceKnown = $baseTicketPrice !== '' ? 'yes' : 'no';
}
$ticketPriceBasis = strtolower(trim((string)($form['air_downgrade_ticket_price_basis'] ?? ($baseTicketPrice !== '' ? 'affected_legs' : 'unknown'))));
if (!in_array($ticketPriceBasis, ['affected_legs', 'whole_ticket', 'unknown'], true)) {
    $ticketPriceBasis = $baseTicketPrice !== '' ? 'affected_legs' : 'unknown';
}
$ticketPrice = (string)($form['air_downgrade_ticket_price'] ?? ($baseTicketPrice !== '' ? preg_replace('/[^0-9.,-]/', '', $baseTicketPrice) : ''));
$ticketPriceCurrency = strtoupper(trim((string)($form['air_downgrade_ticket_price_currency'] ?? $baseTicketCurrency)));
if ($ticketPriceCurrency === '' || $ticketPriceCurrency === 'AUTO') {
    $ticketPriceCurrency = $baseTicketCurrency;
}
$segmentShare = is_numeric($form['downgrade_segment_share'] ?? null)
    ? max(0.0, min(1.0, (float)$form['downgrade_segment_share']))
    : 1.0;
$appliedShare = $ticketPriceBasis === 'whole_ticket' ? $segmentShare : 1.0;
$segmentShareLabel = $ticketPriceBasis === 'whole_ticket'
    ? number_format($segmentShare * 100, 0, ',', '.') . '%'
    : 'Indbygget i prisgrundlag';
$selectedDowngradedLegs = array_values(array_filter((array)($form['leg_downgraded'] ?? []), static fn($value): bool => (string)$value === '1'));
$selectedLegCount = count($selectedDowngradedLegs);
$ticketPriceBasisLabel = match ($ticketPriceBasis) {
    'affected_legs' => 'Pris for markerede downgradede leg(s)',
    'whole_ticket' => 'Pris for hele billetten',
    default => 'Ikke afklaret',
};
$downgradeGate = (string)($form['downgrade_occurred'] ?? '') === 'yes'
    || $selectedRefundPercent !== ''
    || ($bookedClass !== '' && $flownClass !== '' && $bookedClass !== $flownClass);
$ticketPriceNumeric = 0.0;
if ($ticketPrice !== '') {
    $ticketPriceNumeric = (float)str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $ticketPrice));
}
$estimateAmount = ($downgradeGate && $ticketPriceKnown === 'yes' && $ticketPriceNumeric > 0 && $selectedRatePercent > 0)
    ? round($ticketPriceNumeric * ($selectedRatePercent / 100) * $appliedShare, 2)
    : null;
$statusText = !$downgradeGate
    ? 'Ikke aktiveret endnu'
    : ($estimateAmount !== null ? 'Downgrade relevant' : 'Afventer billetpris');
$amountText = !$downgradeGate
    ? 'Ikke aktiveret endnu'
    : ($estimateAmount !== null
        ? number_format($estimateAmount, 2, '.', ',') . ' ' . $ticketPriceCurrency
        : 'Afventer');
$introText = !$downgradeGate
    ? 'Hvis passageren blev placeret i lavere kabineklasse end koebt, vises den foreloebige Article 10-refusion her.'
    : ($estimateAmount !== null
        ? 'Foreloebigt Article 10-beloeb ud fra distancekategori, sats og registreret billetpris.'
        : 'Article 10-satsen er klar, men det endelige beloeb afventer billetpris eller yderligere afklaring.');
?>

<style>
  .air-downgrade-estimate { margin-top:10px; padding:10px; border:1px solid #dbeafe; border-radius:8px; background:#f8fbff; }
  .air-downgrade-estimate-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
  .air-downgrade-estimate-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:700; }
  .air-downgrade-estimate-amount { font-size:22px; font-weight:800; line-height:1.1; color:#0f172a; }
  .air-downgrade-estimate-sub { color:#475569; font-size:12px; }
  .air-downgrade-estimate-grid { margin-top:10px; display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:8px; }
  .air-downgrade-estimate-cell { padding:8px; border-radius:6px; background:#fff; border:1px solid #e2e8f0; }
  .air-downgrade-estimate-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .air-downgrade-estimate-value { margin-top:4px; font-weight:700; color:#1e293b; }
</style>

<div class="air-downgrade-estimate">
  <div class="air-downgrade-estimate-head">
    <div>
      <div><strong>Downgrade-vindue</strong></div>
      <div class="air-downgrade-estimate-sub">Article 10-sporet vises separat fra hovedkompensationen.</div>
    </div>
    <div class="air-downgrade-estimate-status"><?= h($statusText) ?></div>
  </div>

  <div style="margin-top:10px;">
    <div class="air-downgrade-estimate-amount"><?= h($amountText) ?></div>
    <div class="air-downgrade-estimate-sub"><?= h($introText) ?></div>
  </div>

  <div class="air-downgrade-estimate-grid">
    <div class="air-downgrade-estimate-cell">
      <div class="air-downgrade-estimate-label">Distancekategori</div>
      <div class="air-downgrade-estimate-value"><?= h($distanceBandLabel) ?></div>
    </div>
    <div class="air-downgrade-estimate-cell">
      <div class="air-downgrade-estimate-label">Article 10-sats</div>
      <div class="air-downgrade-estimate-value"><?= h($selectedRefundPercent !== '' ? ($selectedRefundPercent . '%') : 'Afventer') ?></div>
    </div>
    <div class="air-downgrade-estimate-cell">
      <div class="air-downgrade-estimate-label">Billetprisgrundlag</div>
      <div class="air-downgrade-estimate-value"><?= h($ticketPriceKnown === 'yes' && $ticketPrice !== '' ? ($ticketPrice . ' ' . $ticketPriceCurrency) : 'Afventer billetpris') ?></div>
    </div>
    <div class="air-downgrade-estimate-cell">
      <div class="air-downgrade-estimate-label">Prisgrundlagstype</div>
      <div class="air-downgrade-estimate-value"><?= h($ticketPriceBasisLabel) ?></div>
    </div>
    <div class="air-downgrade-estimate-cell">
      <div class="air-downgrade-estimate-label">Relevant andel</div>
      <div class="air-downgrade-estimate-value"><?= h($segmentShareLabel) ?></div>
    </div>
    <div class="air-downgrade-estimate-cell">
      <div class="air-downgrade-estimate-label">Markerede downgradede legs</div>
      <div class="air-downgrade-estimate-value"><?= h($selectedLegCount > 0 ? (string)$selectedLegCount : 'Ingen valgt endnu') ?></div>
    </div>
    <div class="air-downgrade-estimate-cell">
      <div class="air-downgrade-estimate-label">Koebt klasse</div>
      <div class="air-downgrade-estimate-value"><?= h($classLabels[$bookedClass] ?? ($bookedClass !== '' ? $bookedClass : 'Ikke oplyst')) ?></div>
    </div>
    <div class="air-downgrade-estimate-cell">
      <div class="air-downgrade-estimate-label">Floejet klasse</div>
      <div class="air-downgrade-estimate-value"><?= h($classLabels[$flownClass] ?? ($flownClass !== '' ? $flownClass : 'Ikke oplyst')) ?></div>
    </div>
  </div>
</div>
