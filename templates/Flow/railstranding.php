<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$v = fn(string $k): string => (string)($form[$k] ?? '');
$priceHints = $priceHints ?? ($meta['price_hints'] ?? $form['price_hints'] ?? []);
$hintText = function (string $key) use ($priceHints): string {
    if (!is_array($priceHints)) {
        return '';
    }
    $h = $priceHints[$key] ?? null;
    if (!is_array($h) || !isset($h['min'], $h['max'], $h['currency'])) {
        return '';
    }
    $min = number_format((float)$h['min'], 0, ',', '.');
    $max = number_format((float)$h['max'], 0, ',', '.');

    return "Typisk interval: {$min}–{$max} {$h['currency']}";
};
$railHintParts = array_values(array_filter([
    ($ht = $hintText('meals')) !== '' ? 'Maaltider / forfriskninger: ' . $ht : '',
    ($ht = $hintText('hotelPerNight')) !== '' ? 'Hotel / overnatning: ' . $ht : '',
    ($ht = $hintText('taxi')) !== '' ? 'Taxi / minibus / lokal transport: ' . $ht : '',
    ($ht = $hintText('altTransport')) !== '' ? 'Alternativ videre transport: ' . $ht : '',
]));
$needsRouter = ((string)($flags['needs_initial_incident_router'] ?? '')) === '1';
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isPreview = !empty($flowPreview);
$prevAction = (is_string($flowPrevAction ?? null) && $flowPrevAction !== '')
    ? $flowPrevAction
    : ($needsRouter ? 'station' : 'entitlements');
$title = match ($travelState) {
    'ongoing' => 'TRIN 3.5 - Strandet paa station (igangvaerende rejse)',
    'completed' => 'TRIN 3.5 - Strandet paa station (afsluttet rejse)',
    default => 'TRIN 3.5 - Strandet paa station',
};
$normalizeStationName = static function (?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/\s+Gl\.?\s*[0-9A-Za-z+\-\/]+$/u', '', $value) ?? $value;
    $value = preg_replace('/\s+Gleis\s+[0-9A-Za-z+\-\/]+$/ui', '', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
};
$railStrandingContext = $v('rail_stranding_context') !== '' ? $v('rail_stranding_context') : 'no';
$railProblemAnchor = (array)($meta['rail_problem_anchor'] ?? []);
$railCurrentLocationAnchor = (array)($meta['rail_current_location_anchor'] ?? []);
$suggestedStation = $normalizeStationName((string)(
    ($form['stranded_current_station'] ?? '')
    ?: ($railProblemAnchor['station_name'] ?? '')
    ?: ($form['dep_station'] ?? '')
    ?: ($railCurrentLocationAnchor['station_name'] ?? '')
));
$selectedDeparture = (array)($meta['rail_selected_departure'] ?? []);
$contractOptions = (array)($meta['contract_options'] ?? []);
$contractModel = strtolower(trim((string)($form['contract_model'] ?? (($meta['rail_contract_structure_seed'] ?? [])['effective_contract_model'] ?? ''))));
$problemContractId = trim((string)($form['problem_contract_id'] ?? (($meta['rail_contract_structure_seed'] ?? [])['problem_contract_id'] ?? '')));
$selectedContract = ($contractModel === 'separate' && $problemContractId !== '' && isset($contractOptions[$problemContractId]) && is_array($contractOptions[$problemContractId]))
    ? (array)$contractOptions[$problemContractId]
    : [];
$selectedStation = trim((string)($form['stranded_current_station'] ?? ''));
$selectedStation = $selectedStation !== '' && $selectedStation !== 'other' ? $normalizeStationName($selectedStation) : $selectedStation;
$selectedStationOther = trim((string)($form['stranded_current_station_other'] ?? ''));
$railStationExpensesSignal = strtolower(trim((string)($form['rail_station_expenses_signal'] ?? '')));
$railStationExpenseTypes = (array)($form['rail_station_expense_types'] ?? []);
$railStationStillThere = strtolower(trim((string)($form['rail_station_still_there'] ?? '')));
$railStationWhereEnded = strtolower(trim((string)($form['rail_station_where_ended'] ?? '')));
$railStationEndStation = trim((string)($form['rail_station_end_station'] ?? ''));
$railStationEndStation = $railStationEndStation !== '' && $railStationEndStation !== 'other' ? $normalizeStationName($railStationEndStation) : $railStationEndStation;
$railStationEndStationOther = trim((string)($form['rail_station_end_station_other'] ?? ''));
if (!in_array($railStationStillThere, ['yes', 'no'], true)) {
    $railStationStillThere = $railStationWhereEnded === '' || $railStationWhereEnded === 'same_station' ? 'yes' : 'no';
}

$stationOptionsMap = [];
$stationSequence = 0;
$addStation = static function (array &$stationOptionsMap, int &$stationSequence, ?string $value, int $priority = 50) use ($normalizeStationName): void {
    $raw = trim((string)$value);
    $value = $normalizeStationName($raw);
    if ($value === '' || $value === 'other' || $value === 'unknown') {
        return;
    }
    $key = mb_strtolower($value, 'UTF-8');
    if (!isset($stationOptionsMap[$key])) {
        $stationOptionsMap[$key] = [
            'label' => $value,
            'priority' => $priority,
            'sequence' => $stationSequence++,
        ];
        return;
    }
    if ($priority < (int)$stationOptionsMap[$key]['priority']) {
        $stationOptionsMap[$key]['priority'] = $priority;
    }
};

$isContractScoped = !empty($selectedContract);
if ($isContractScoped) {
    $contractStops = array_values(array_filter((array)($selectedContract['stops'] ?? []), static fn($stop): bool => is_array($stop)));
    foreach ($contractStops as $stop) {
        $addStation($stationOptionsMap, $stationSequence, (string)($stop['name'] ?? ''), 10);
    }

    $firstContractStop = $contractStops[0] ?? [];
    $lastContractStop = $contractStops !== [] ? $contractStops[count($contractStops) - 1] : [];
    $fromStopName = $normalizeStationName((string)($firstContractStop['name'] ?? ''));
    $toStopName = $normalizeStationName((string)($lastContractStop['name'] ?? ''));
    $callingPointNames = [];
    foreach ((array)($selectedDeparture['calling_points'] ?? []) as $point) {
        if (!is_array($point)) {
            continue;
        }
        $stationName = $normalizeStationName((string)($point['station_name'] ?? ''));
        if ($stationName !== '') {
            $callingPointNames[] = $stationName;
        }
    }
    if ($fromStopName !== '' && $toStopName !== '' && !empty($callingPointNames)) {
        $fromIndex = array_search($fromStopName, $callingPointNames, true);
        $toIndex = array_search($toStopName, $callingPointNames, true);
        if ($fromIndex !== false && $toIndex !== false) {
            $sliceStart = min((int)$fromIndex, (int)$toIndex);
            $sliceEnd = max((int)$fromIndex, (int)$toIndex);
            foreach (array_slice($callingPointNames, $sliceStart, $sliceEnd - $sliceStart + 1) as $stationName) {
                $addStation($stationOptionsMap, $stationSequence, $stationName, 20);
            }
        }
    }
} else {
    $addStation($stationOptionsMap, $stationSequence, $selectedDeparture['origin_station_name'] ?? '', 20);
    $addStation($stationOptionsMap, $stationSequence, $selectedDeparture['destination_station_name'] ?? '', 20);
    $addStation($stationOptionsMap, $stationSequence, $form['dep_station'] ?? '', 20);
    $addStation($stationOptionsMap, $stationSequence, $form['arr_station'] ?? '', 20);

    foreach ((array)(($selectedDeparture['raw'] ?? [])['transfer_station_names'] ?? []) as $stationName) {
        $addStation($stationOptionsMap, $stationSequence, (string)$stationName, 10);
    }

    foreach ((array)($selectedDeparture['calling_points'] ?? []) as $point) {
        if (!is_array($point)) {
            continue;
        }
        $addStation($stationOptionsMap, $stationSequence, (string)($point['station_name'] ?? ''), 30);
    }
    foreach ((array)($journey['segments'] ?? []) as $segment) {
        if (!is_array($segment)) {
            continue;
        }
        $addStation($stationOptionsMap, $stationSequence, (string)($segment['from_name'] ?? ($segment['from'] ?? '')), 30);
        $addStation($stationOptionsMap, $stationSequence, (string)($segment['to_name'] ?? ($segment['to'] ?? '')), 30);
    }
}

$addStation($stationOptionsMap, $stationSequence, $railProblemAnchor['station_name'] ?? '', 0);
$addStation($stationOptionsMap, $stationSequence, $railCurrentLocationAnchor['station_name'] ?? '', 0);
$addStation($stationOptionsMap, $stationSequence, $form['handoff_station'] ?? '', 0);
$addStation($stationOptionsMap, $stationSequence, $selectedStation !== 'other' ? $selectedStation : '', 0);
$addStation($stationOptionsMap, $stationSequence, $selectedStationOther, 0);
$addStation($stationOptionsMap, $stationSequence, $suggestedStation, 0);

$stationOptions = array_values(array_map(
    static fn(array $entry): string => (string)$entry['label'],
    array_values($stationOptionsMap)
));
usort($stationOptions, static function (string $left, string $right) use ($stationOptionsMap): int {
    $leftKey = mb_strtolower($left, 'UTF-8');
    $rightKey = mb_strtolower($right, 'UTF-8');
    $leftMeta = $stationOptionsMap[$leftKey] ?? ['priority' => 99, 'sequence' => 9999];
    $rightMeta = $stationOptionsMap[$rightKey] ?? ['priority' => 99, 'sequence' => 9999];
    if ((int)$leftMeta['priority'] !== (int)$rightMeta['priority']) {
        return (int)$leftMeta['priority'] <=> (int)$rightMeta['priority'];
    }
    if ((int)$leftMeta['sequence'] !== (int)$rightMeta['sequence']) {
        return (int)$leftMeta['sequence'] <=> (int)$rightMeta['sequence'];
    }

    return strnatcasecmp($left, $right);
});

if ($selectedStation === '' && $suggestedStation !== '') {
    if (in_array($suggestedStation, $stationOptions, true)) {
        $selectedStation = $suggestedStation;
    } else {
        $selectedStation = 'other';
        $selectedStationOther = $suggestedStation;
    }
}
?>

<style>
  .card { padding: 12px; border: 1px solid #ddd; border-radius: 6px; background:#fff; }
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  [data-show-if] { display:none; }
  select, input[type="text"] { max-width: 520px; width: 100%; }
</style>

<h1><?= h($title) ?></h1>
<?= $this->element('rail_live_estimate', compact('form', 'flags', 'meta', 'journey')) ?>
<p class="small muted">Dette rail-specifikke trin bruges kun til station-kontekst. Strandet paa sporet / i toget afklares senere efter incident-trinnet, hvis det bliver relevant.</p>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<div class="card mt12">
  <strong>Station-kontekst</strong>
  <?php if ($railHintParts !== []): ?>
  <div class="small mt8" style="background:#eef7ff; padding:8px; border-radius:6px;">
    <strong>Vejledende rail-niveauer (ikke faste juridiske caps)</strong>
    <div class="muted mt4">Brug disse som pejlemaerker, hvis du bliver noedt til selv at afholde udgifter fra stationen. Endelig rail-vurdering og dokumentation samles senere i backend-sagen.</div>
    <div class="muted mt4"><?= h(implode(' | ', $railHintParts)) ?></div>
  </div>
  <?php endif; ?>

  <div class="mt8">
    <div>Er du strandet paa en station lige nu?</div>
    <label><input type="radio" name="rail_stranding_context" value="no" <?= $railStrandingContext === 'no' ? 'checked' : '' ?> /> Nej</label>
    <label class="ml8"><input type="radio" name="rail_stranding_context" value="station" <?= $railStrandingContext === 'station' ? 'checked' : '' ?> /> Ja</label>
  </div>

  <div data-show-if="rail_stranding_context:station">

  <div class="mt8 small muted" data-show-if="rail_stranding_context:station">
    Brug dette ogsaa hvis du strandede ved startstationen, selv om der ikke var skift i billetten.
  </div>

  <?php if ($suggestedStation !== ''): ?>
    <div class="mt8 small muted" data-show-if="rail_stranding_context:station">
      Systemet foreslaar stationen, hvor stationssituationen opstod: <strong><?= h($suggestedStation) ?></strong>
    </div>
  <?php endif; ?>

  <?php if ($isContractScoped): ?>
    <div class="mt8 small muted" data-show-if="rail_stranding_context:station">
      Listen er afgraenset til den valgte problemkontrakt. Vaelg <strong>Anden station</strong>, hvis du er et andet sted paa rejsen.
    </div>
  <?php else: ?>
    <div class="mt8 small muted" data-show-if="rail_stranding_context:station">
      Listen viser hele den valgte rejse, men prioriterer foreslaaet station, problemsted og skift oeverst.
    </div>
  <?php endif; ?>

  <div class="mt12" data-show-if="rail_stranding_context:station">
    <label for="railStrandedCurrentStation"><strong>Hvilken station strandede du paa?</strong></label>
    <select id="railStrandedCurrentStation" name="stranded_current_station" class="mt4">
      <option value="">Vaelg station</option>
      <?php foreach ($stationOptions as $stationName): ?>
        <option value="<?= h($stationName) ?>" <?= $selectedStation === $stationName ? 'selected' : '' ?>><?= h($stationName) ?></option>
      <?php endforeach; ?>
      <option value="other" <?= $selectedStation === 'other' ? 'selected' : '' ?>>Anden station</option>
    </select>
  </div>

  <div class="mt8" data-show-if="stranded_current_station:other">
    <label for="railStrandedCurrentStationOther"><strong>Anden station</strong></label>
    <input id="railStrandedCurrentStationOther" type="text" name="stranded_current_station_other" value="<?= h($selectedStationOther) ?>" placeholder="Skriv stationen her" />
  </div>

  <?php if ($travelState === 'ongoing'): ?>
  <div class="mt12" data-show-if="rail_stranding_context:station">
    <div><strong>Er du stadig paa den station lige nu?</strong></div>
    <label class="mt8" style="display:inline-block;"><input type="radio" name="rail_station_still_there" value="yes" <?= $railStationStillThere === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8" style="display:inline-block;"><input type="radio" name="rail_station_still_there" value="no" <?= $railStationStillThere === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>
  <?php endif; ?>

  <div class="mt12" data-show-if="rail_stranding_context:station">
    <div><strong>Har du allerede haft eller forventer du noedvendige udgifter fra denne station?</strong></div>
    <div class="small muted mt4">Bruges kun som tidligt signal i rail-flowet. Konkrete rail-udgifter registreres senere i backend-sagen.</div>
    <label class="mt8" style="display:inline-block;"><input type="radio" name="rail_station_expenses_signal" value="yes" <?= $railStationExpensesSignal === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8" style="display:inline-block;"><input type="radio" name="rail_station_expenses_signal" value="no" <?= $railStationExpensesSignal === 'no' ? 'checked' : '' ?> /> Nej</label>
    <label class="ml8" style="display:inline-block;"><input type="radio" name="rail_station_expenses_signal" value="unknown" <?= $railStationExpensesSignal === 'unknown' ? 'checked' : '' ?> /> Ved ikke endnu</label>
  </div>

  <div class="mt8" data-show-if="rail_station_expenses_signal:yes">
    <div class="small muted">Hvilke typer forventer du eller har du haft?</div>
    <?php
      $expenseTypeChecked = static function (string $key) use ($railStationExpenseTypes): string {
          return in_array($key, $railStationExpenseTypes, true) ? 'checked' : '';
      };
    ?>
    <div class="mt4 small muted">Assistance / care</div>
    <label class="mt4" style="display:block;"><input type="checkbox" name="rail_station_expense_types[]" value="meals" <?= $expenseTypeChecked('meals') ?> /> Maaltider / forfriskninger</label>
    <?php if ($ht = $hintText('meals')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
    <label style="display:block;"><input type="checkbox" name="rail_station_expense_types[]" value="hotel" <?= $expenseTypeChecked('hotel') ?> /> Hotel / overnatning</label>
    <?php if ($ht = $hintText('hotelPerNight')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
    <label style="display:block;"><input type="checkbox" name="rail_station_expense_types[]" value="local_transport" <?= $expenseTypeChecked('local_transport') ?> /> Lokal transport til/fra station eller hotel</label>
    <?php if ($ht = $hintText('taxi')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
    <div class="mt8 small muted">Videre rejse paa egen haand</div>
    <label class="mt4" style="display:block;"><input type="checkbox" name="rail_station_expense_types[]" value="train" <?= $expenseTypeChecked('train') ?> /> Andet tog</label>
    <label style="display:block;"><input type="checkbox" name="rail_station_expense_types[]" value="bus" <?= $expenseTypeChecked('bus') ?> /> Bus</label>
    <label style="display:block;"><input type="checkbox" name="rail_station_expense_types[]" value="taxi" <?= $expenseTypeChecked('taxi') ?> /> Taxi / minibus</label>
    <label style="display:block;"><input type="checkbox" name="rail_station_expense_types[]" value="rideshare" <?= $expenseTypeChecked('rideshare') ?> /> Samkoersel / rideshare</label>
    <label style="display:block;"><input type="checkbox" name="rail_station_expense_types[]" value="other" <?= $expenseTypeChecked('other') ?> /> Andet</label>
    <?php if ($ht = $hintText('altTransport')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
  </div>

  <div class="mt12" data-show-if="<?= $travelState === 'ongoing' ? 'rail_station_still_there:no' : 'rail_stranding_context:station' ?>">
    <label for="railStationWhereEnded"><strong><?= $travelState === 'completed' ? 'Hvor endte du efter denne situation?' : 'Hvor er du nu / hvor er du foreloebigt endt siden da?' ?></strong></label>
    <select id="railStationWhereEnded" name="rail_station_where_ended" class="mt4">
      <option value="">Vaelg</option>
      <?php if ($travelState === 'completed'): ?>
        <option value="same_station" <?= $railStationWhereEnded === 'same_station' ? 'selected' : '' ?>>Jeg endte paa denne station</option>
      <?php endif; ?>
      <option value="other_station" <?= $railStationWhereEnded === 'other_station' ? 'selected' : '' ?>>Jeg kom videre til en anden station</option>
      <option value="return_to_departure" <?= $railStationWhereEnded === 'return_to_departure' ? 'selected' : '' ?>>Jeg vendte tilbage til afgangsstationen</option>
      <option value="final_destination" <?= $railStationWhereEnded === 'final_destination' ? 'selected' : '' ?>>Jeg naaede min endelige destination</option>
      <option value="unknown" <?= $railStationWhereEnded === 'unknown' ? 'selected' : '' ?>>Ved ikke endnu</option>
    </select>
    <div class="small muted mt4">Bruges til at situere de senere rail-trin. Endelig Art. 20-transportafklaring kan stadig komme senere i flowet.</div>
    <div class="mt8" data-show-if="rail_station_where_ended:other_station">
      <label for="railStationEndStation"><strong>Hvilken station kom du videre til?</strong></label>
      <select id="railStationEndStation" name="rail_station_end_station" class="mt4">
        <option value="">Vaelg station</option>
        <?php foreach ($stationOptions as $stationName): ?>
          <option value="<?= h($stationName) ?>" <?= $railStationEndStation === $stationName ? 'selected' : '' ?>><?= h($stationName) ?></option>
        <?php endforeach; ?>
        <option value="other" <?= $railStationEndStation === 'other' ? 'selected' : '' ?>>Anden station</option>
      </select>
    </div>

    <div class="mt8" data-show-if="rail_station_end_station:other">
      <label for="railStationEndStationOther"><strong>Anden slutstation</strong></label>
      <input id="railStationEndStationOther" type="text" name="rail_station_end_station_other" value="<?= h($railStationEndStationOther) ?>" placeholder="Skriv stationen her" />
    </div>
  </div>
  </div>
</div>

<div class="mt12" style="display:flex; gap:8px; align-items:center;">
  <?= $this->Html->link('<- Tilbage', ['action' => $prevAction], ['class' => 'button', 'style' => 'background:#eee; color:#333;', 'escape' => false]) ?>
  <?= $this->Form->button('Naeste trin ->', ['class' => 'button']) ?>
</div>

</fieldset>
<?= $this->Form->end() ?>

<script>
function updateReveal() {
  document.querySelectorAll('[data-show-if]').forEach(function(el) {
    var spec = el.getAttribute('data-show-if');
    if (!spec) return;
    var parts = spec.split(':');
    if (parts.length !== 2) return;
    var name = parts[0];
    var valid = parts[1].split(',');
    var radio = document.querySelector('input[name="' + name + '"]:checked');
    var select = document.querySelector('select[name="' + name + '"]');
    var value = radio ? radio.value : (select ? select.value : '');
    var show = value && valid.includes(value);
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  });
}
document.addEventListener('change', function(e) {
  if (!e.target || !e.target.name) return;
  updateReveal();
});
document.addEventListener('DOMContentLoaded', updateReveal);
</script>
