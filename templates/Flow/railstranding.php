<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$v = fn(string $k): string => (string)($form[$k] ?? '');
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
$railStrandingContext = $v('rail_stranding_context') !== '' ? $v('rail_stranding_context') : 'no';
$railProblemAnchor = (array)($meta['rail_problem_anchor'] ?? []);
$railCurrentLocationAnchor = (array)($meta['rail_current_location_anchor'] ?? []);
$suggestedStation = trim((string)($railCurrentLocationAnchor['station_name'] ?? ($railProblemAnchor['station_name'] ?? ($form['dep_station'] ?? ''))));
$selectedDeparture = (array)($meta['rail_selected_departure'] ?? []);
$selectedStation = trim((string)($form['stranded_current_station'] ?? ''));
$selectedStationOther = trim((string)($form['stranded_current_station_other'] ?? ''));

$stationSet = [];
$addStation = static function (array &$stationSet, ?string $value): void {
    $value = trim((string)$value);
    if ($value === '' || $value === 'other' || $value === 'unknown') {
        return;
    }
    $stationSet[$value] = true;
};

$addStation($stationSet, $form['dep_station'] ?? '');
$addStation($stationSet, $form['arr_station'] ?? '');
$addStation($stationSet, $railProblemAnchor['station_name'] ?? '');
$addStation($stationSet, $railCurrentLocationAnchor['station_name'] ?? '');
$addStation($stationSet, $form['handoff_station'] ?? '');
$addStation($stationSet, $selectedDeparture['origin_station_name'] ?? '');
$addStation($stationSet, $selectedDeparture['destination_station_name'] ?? '');

foreach ((array)($selectedDeparture['calling_points'] ?? []) as $point) {
    if (!is_array($point)) {
        continue;
    }
    $addStation($stationSet, (string)($point['station_name'] ?? ''));
}
foreach ((array)(($selectedDeparture['raw'] ?? [])['transfer_station_names'] ?? []) as $stationName) {
    $addStation($stationSet, (string)$stationName);
}
foreach ((array)($journey['segments'] ?? []) as $segment) {
    if (!is_array($segment)) {
        continue;
    }
    $addStation($stationSet, (string)($segment['from_name'] ?? ($segment['from'] ?? '')));
    $addStation($stationSet, (string)($segment['to_name'] ?? ($segment['to'] ?? '')));
}

$stationOptions = array_keys($stationSet);
sort($stationOptions, SORT_NATURAL | SORT_FLAG_CASE);

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
<p class="small muted">Dette rail-specifikke trin bruges kun til station-kontekst. Strandet paa sporet / i toget afklares senere efter incident-trinnet, hvis det bliver relevant.</p>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<div class="card mt12">
  <strong>Station-kontekst</strong>

  <div class="mt8">
    <div>Er du strandet paa en station lige nu?</div>
    <label><input type="radio" name="rail_stranding_context" value="no" <?= $railStrandingContext === 'no' ? 'checked' : '' ?> /> Nej</label>
    <label class="ml8"><input type="radio" name="rail_stranding_context" value="station" <?= $railStrandingContext === 'station' ? 'checked' : '' ?> /> Ja</label>
  </div>

  <div class="mt8 small muted" data-show-if="rail_stranding_context:station">
    Brug dette ogsaa hvis du strandede ved startstationen, selv om der ikke var skift i billetten.
  </div>

  <?php if ($suggestedStation !== ''): ?>
    <div class="mt8 small muted" data-show-if="rail_stranding_context:station">
      Systemet foreslaar station: <strong><?= h($suggestedStation) ?></strong>
    </div>
  <?php endif; ?>

  <div class="mt12" data-show-if="rail_stranding_context:station">
    <label for="railStrandedCurrentStation"><strong>Hvilken station er du ved nu?</strong></label>
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
