<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$profile = $profile ?? ['articles' => []];
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isCompleted = ($travelState === 'completed');
$isOngoing = ($travelState === 'ongoing');
$transportHint = 'Alternativ transport skal tilbydes, hvis du er strandet pga. aflysning/forsinkelse.';
if ($isOngoing) { $transportHint .= ' (Udfyld det, der er sket indtil nu).'; }
$journeyOutcomeLabel = $isOngoing ? 'Hvordan forventer du, at din rejse ender?' : 'Hvordan endte din rejse?';
$journeyOutcomeHint = $isOngoing ? 'Vi har sat dette ud fra dine svar indtil nu. Ret hvis det aendrer sig.' : 'Vi har sat dette ud fra dine svar. Ret hvis forkert.';
$transportTitle = $isOngoing
    ? 'TRIN 5 - Transport til/fra (igangvaerende rejse)'
    : ($isCompleted ? 'TRIN 5 - Transport til/fra (afsluttet rejse)' : 'TRIN 5 - Transport til/fra (Art. 20)');

$segments = [];
if (!empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) { $segments = (array)$meta['_segments_auto']; }
elseif (!empty($meta['_segments_all']) && is_array($meta['_segments_all'])) { $segments = (array)$meta['_segments_all']; }
$stations = [];
$addStation = function ($val) use (&$stations) {
    $s = trim((string)$val);
    if ($s === '') { return; }
    $stations[$s] = true;
};
foreach ($segments as $seg) {
    if (!is_array($seg)) { continue; }
    foreach (['from','to','origin','destination','dep_station','arr_station','departureStation','arrivalStation'] as $k) {
        if (isset($seg[$k])) { $addStation($seg[$k]); }
    }
}
$addStation($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ''));
$addStation($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ''));
$stationOptions = array_keys($stations);
sort($stationOptions, SORT_NATURAL | SORT_FLAG_CASE);
?>

<style>
    .card { padding: 12px; border: 1px solid #ddd; border-radius: 6px; background:#fff; }
    .hidden { display:none; }
    .small { font-size:12px; }
    .muted { color:#666; }
    .mt4 { margin-top:4px; }
    .mt8 { margin-top:8px; }
    .mt12 { margin-top:12px; }
    .ml8 { margin-left:8px; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .disabled-block { opacity: 0.6; }
    .flow-wrapper { max-width: 1100px; margin: 0 auto; }
    .flow-wide { width: 100%; }
    .card-title { display:flex; align-items:center; gap:8px; font-weight:600; }
    .card-title .icon { font-size:16px; line-height:1; }
</style>
<div class="flow-wrapper">
<h1><?= h($transportTitle) ?></h1>
<?php
    if ($travelState === 'completed') {
        echo '<p class="small muted">Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.</p>';
    } elseif ($travelState === 'ongoing') {
        echo '<p class="small muted">Status: Rejsen er i gang. Vi samler dine valg for resten af forloebet.</p>';
    } elseif ($travelState === 'not_started') {
        echo '<p class="small muted">Status: Rejsen er endnu ikke paabegyndt. Besvar ud fra, hvad du forventer at goere ved forsinkelse/aflysning.</p>';
    }
?>
<?php
    $articles = (array)($profile['articles'] ?? []);
    // Art. 20 gating (moved from incident.php)
    $art20TrackOff   = ($articles['art20_2c'] ?? ($articles['art20_2'] ?? true)) === false;
    $art20StationOff = ($articles['art20_3'] ?? true) === false;
    $art20FullyOff   = ($articles['art20_2'] ?? true) === false && $art20TrackOff && $art20StationOff;
    $missedSrc = (string)($incident['missed_source'] ?? '');
    $forceStationOnly = !empty($incident['missed']) && $missedSrc === 'incident_form';
    $art20Disabled = $art20FullyOff && !$forceStationOnly;
    $showTrack = !$forceStationOnly && !$art20TrackOff;
    $showStation = $forceStationOnly ? true : !$art20StationOff;
    $showIrrelevant = !$forceStationOnly;
    $v = fn(string $k): string => (string)($form[$k] ?? '');
?>
<?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'choices'], 'type' => 'file', 'novalidate' => true]) ?>

<div id="coreAfterArt20">
    <div id="art20Wrapper" class="card mt12 <?= $art20Disabled ? 'hidden' : '' ?>" data-art="20" data-art20-disabled="<?= $art20Disabled ? '1' : '0' ?>">
        <div class="card-title"><span class="icon">&#128652;</span><span>Transport til/fra (Art.20)</span></div>
        <p class="small muted"><?= h($transportHint) ?></p>

        <div class="mt8">
            <div>Hvor var du, da det skete? (vaelg en)</div>
            <?php if ($showTrack): ?>
                <label data-art="20(2c)"><input type="radio" name="stranded_location" value="track" <?= $v('stranded_location')==='track'?'checked':'' ?> /> Jeg sad fast i toget paa sporet</label>
            <?php endif; ?>
            <?php if ($showStation): ?>
                <label class="<?= $showTrack ? 'ml8' : '' ?>" data-art="20(3)"><input type="radio" name="stranded_location" value="station" <?= $v('stranded_location')==='station'?'checked':'' ?> /> Jeg var paa en station uden videre tog</label>
            <?php endif; ?>
            <?php if ($showIrrelevant): ?>
                <label class="ml8"><input type="radio" name="stranded_location" value="irrelevant" <?= $v('stranded_location')==='irrelevant'?'checked':'' ?> /> Ikke relevant / andet</label>
            <?php endif; ?>
        </div>

        <div class="mt4 <?= $showTrack ? '' : 'hidden' ?>" data-show-if="stranded_location:track" data-art="20(2c)">
            <span>Blev der stillet transport til raadighed for at komme vaek/videre?</span>
            <?php $bt = $v('blocked_train_alt_transport'); ?>
            <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej</label>
            <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="irrelevant" <?= $bt==='irrelevant'?'checked':'' ?> /> Ikke relevant / andet</label>
        </div>

        <div class="mt4 <?= $showTrack ? '' : 'hidden' ?>" data-show-if="blocked_train_alt_transport:yes" data-art="20(2c)">
            <div class="grid-2">
                <label>Transporttype
                    <select name="assistance_alt_transport_type">
                        <?php foreach (['rail'=>'Tog','bus'=>'Bus','taxi'=>'Taxi','other'=>'Andet'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $v('assistance_alt_transport_type')===$val?'selected':'' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Hvor blev du koert til?
                    <?php $to = $v('assistance_alt_to_destination'); ?>
                    <select name="assistance_alt_to_destination">
                        <option value="">Vaelg</option>
                        <option value="nearest_station" <?= $to==='nearest_station'?'selected':'' ?>>Naermeste station</option>
                        <option value="other_departure_point" <?= $to==='other_departure_point'?'selected':'' ?>>Et andet egnet afgangssted</option>
                        <option value="final_destination" <?= $to==='final_destination'?'selected':'' ?>>Mit endelige bestemmelsessted</option>
                    </select>
                </label>
                <label data-show-if="assistance_alt_to_destination:nearest_station,other_departure_point">Hvilken station endte du ved?
                    <?php $arrSt = $v('assistance_alt_arrival_station'); ?>
                    <select name="assistance_alt_arrival_station">
                        <option value="">Vaelg</option>
                        <?php foreach ($stationOptions as $st): ?>
                            <option value="<?= h($st) ?>" <?= $arrSt===$st?'selected':'' ?>><?= h($st) ?></option>
                        <?php endforeach; ?>
                        <option value="unknown" <?= $arrSt==='unknown'?'selected':'' ?>>Ved ikke</option>
                        <option value="other" <?= $arrSt==='other'?'selected':'' ?>>Anden station</option>
                    </select>
                    <input type="text" name="assistance_alt_arrival_station_other" value="<?= h($v('assistance_alt_arrival_station_other')) ?>" placeholder="Anden station" data-show-if="assistance_alt_arrival_station:other" />
                </label>
                <label>Hvem organiserede transporten?
                    <select name="assistance_alt_transport_offered_by">
                        <?php foreach (['operator'=>'Operatoer','station_manager'=>'Station','ticket_retailer'=>'Retailer/billetudsteder','other'=>'Andet'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $v('assistance_alt_transport_offered_by')===$val?'selected':'' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>

        <div class="mt4 <?= $showTrack ? '' : 'hidden' ?>" data-show-if="blocked_train_alt_transport:no" data-art="20(2c)">
            <div>Hvad gjorde du saa?</div>
            <?php $bn = $v('blocked_no_transport_action'); ?>
            <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="waited" <?= $bn==='waited'?'checked':'' ?> /> Ventede til toget kunne koere videre</label>
            <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="walked_station" <?= $bn==='walked_station'?'checked':'' ?> /> Fandt selv vej til station/spor</label>
            <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="evacuated_later" <?= $bn==='evacuated_later'?'checked':'' ?> /> Blev evakueret senere</label>
            <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="self_arranged" <?= $bn==='self_arranged'?'checked':'' ?> /> Fandt selv transport</label>
            <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="other" <?= $bn==='other'?'checked':'' ?> /> Andet</label>
        </div>

        <div class="mt4 <?= $showTrack ? '' : 'hidden' ?>" data-show-if="blocked_no_transport_action:self_arranged" data-art="20(2c)">
            <div class="small muted">Angiv egne udgifter hvis du selv ordnede transport.</div>
            <div class="grid-2 mt4">
                <label>Transporttype
                    <?php $bst = $v('blocked_self_paid_transport_type'); ?>
                    <select name="blocked_self_paid_transport_type">
                        <option value="">Vaelg</option>
                        <option value="rail" <?= $bst==='rail'?'selected':'' ?>>Tog</option>
                        <option value="bus" <?= $bst==='bus'?'selected':'' ?>>Bus</option>
                        <option value="taxi" <?= $bst==='taxi'?'selected':'' ?>>Taxi</option>
                        <option value="rideshare" <?= $bst==='rideshare'?'selected':'' ?>>Samkoersel/rideshare</option>
                        <option value="other" <?= $bst==='other'?'selected':'' ?>>Andet</option>
                    </select>
                </label>
                <label>Beloeb
                    <input type="number" step="0.01" name="blocked_self_paid_amount" value="<?= h($v('blocked_self_paid_amount')) ?>" />
                </label>
                <label>Valuta (fx DKK/EUR)
                    <input type="text" name="blocked_self_paid_currency" value="<?= h($v('blocked_self_paid_currency')) ?>" />
                </label>
                <label class="small">Kvittering
                    <input type="file" name="blocked_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
                </label>
            </div>
            <?php if ($f = $v('blocked_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
        </div>

        <div class="mt4 <?= $showStation ? '' : 'hidden' ?>" data-show-if="stranded_location:station" data-art="20(3)">
            <div>Fik du tilbudt en loesning for at komme videre?</div>
            <?php $sol = $v('a20_3_solution_offered'); ?>
            <label><input type="radio" name="a20_3_solution_offered" value="yes" <?= $sol==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="a20_3_solution_offered" value="no" <?= $sol==='no'?'checked':'' ?> /> Nej</label>
        </div>

        <div class="mt4 <?= $showStation ? '' : 'hidden' ?>" data-show-if="a20_3_solution_offered:yes" data-art="20(3)">
            <div class="grid-2">
                <label>Hvilken loesning blev tilbudt?
                    <?php $stype = $v('a20_3_solution_type'); ?>
                    <select name="a20_3_solution_type">
                        <option value="">Vaelg</option>
                        <option value="rebooking_rail" <?= $stype==='rebooking_rail'?'selected':'' ?>>Omlagt til andet tog/anden rute</option>
                        <option value="bus" <?= $stype==='bus'?'selected':'' ?>>Erstatningsbus</option>
                        <option value="taxi" <?= $stype==='taxi'?'selected':'' ?>>Taxi/minibus</option>
                        <option value="wait_next" <?= $stype==='wait_next'?'selected':'' ?>>Bedt om at vente paa naeste afgang</option>
                        <option value="hotel" <?= $stype==='hotel'?'selected':'' ?>>Hotel/indkvartering</option>
                        <option value="refund" <?= $stype==='refund'?'selected':'' ?>>Refusion</option>
                        <option value="other" <?= $stype==='other'?'selected':'' ?>>Andet</option>
                    </select>
                </label>
                <label>Hvor endte du?
                    <?php $to2 = $v('a20_3_to_destination') ?: $v('assistance_alt_to_destination'); ?>
                    <select name="a20_3_to_destination">
                        <option value="">Vaelg</option>
                        <option value="nearest_station" <?= $to2==='nearest_station'?'selected':'' ?>>Naermeste station</option>
                        <option value="other_departure_point" <?= $to2==='other_departure_point'?'selected':'' ?>>Et andet egnet afgangssted</option>
                        <option value="final_destination" <?= $to2==='final_destination'?'selected':'' ?>>Mit endelige bestemmelsessted</option>
                    </select>
                    <div class="small muted mt4" data-show-if="a20_3_to_destination:nearest_station,other_departure_point">Angiv station, saa vi kan beregne downgrade korrekt.</div>
                </label>
                <label class="mt4" data-show-if="a20_3_to_destination:nearest_station,other_departure_point">Hvilken station endte du ved?
                    <?php $arrSt2 = $v('a20_3_arrival_station') ?: $v('assistance_alt_arrival_station'); ?>
                    <select name="a20_3_arrival_station">
                        <option value="">Vaelg</option>
                        <?php foreach ($stationOptions as $st): ?>
                            <option value="<?= h($st) ?>" <?= $arrSt2===$st?'selected':'' ?>><?= h($st) ?></option>
                        <?php endforeach; ?>
                        <option value="unknown" <?= $arrSt2==='unknown'?'selected':'' ?>>Ved ikke</option>
                        <option value="other" <?= $arrSt2==='other'?'selected':'' ?>>Anden station</option>
                    </select>
                    <?php $arrOther2 = $v('a20_3_arrival_station_other') ?: $v('assistance_alt_arrival_station_other'); ?>
                    <input type="text" name="a20_3_arrival_station_other" value="<?= h($arrOther2) ?>" placeholder="Anden station" data-show-if="a20_3_arrival_station:other" />
                </label>
                <label>Hvem organiserede?
                    <?php $sob = $v('a20_3_solution_offered_by'); ?>
                    <select name="a20_3_solution_offered_by">
                        <option value="">Vaelg</option>
                        <option value="operator" <?= $sob==='operator'?'selected':'' ?>>Operatoer</option>
                        <option value="station_manager" <?= $sob==='station_manager'?'selected':'' ?>>Station</option>
                        <option value="ticket_retailer" <?= $sob==='ticket_retailer'?'selected':'' ?>>Retailer/billetudsteder</option>
                        <option value="other" <?= $sob==='other'?'selected':'' ?>>Andet</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="mt4 <?= $showStation ? '' : 'hidden' ?>" data-show-if="a20_3_solution_offered:no" data-art="20(3)">
            <div class="small muted">Hvis du ikke fik en loesning, kan du angive hvordan du selv haandterede situationen og evt. egne udgifter.</div>
            <div class="mt4">Hvordan haandterede du situationen?</div>
            <?php $ns = $v('a20_3_no_solution_action'); ?>
            <label class="ml8"><input type="radio" name="a20_3_no_solution_action" value="self_arranged" <?= $ns==='self_arranged'?'checked':'' ?> /> Jeg fandt selv transport</label>
            <label class="ml8"><input type="radio" name="a20_3_no_solution_action" value="went_home" <?= $ns==='went_home'?'checked':'' ?> /> Jeg tog hjem</label>
            <label class="ml8"><input type="radio" name="a20_3_no_solution_action" value="abandoned" <?= $ns==='abandoned'?'checked':'' ?> /> Jeg opgav rejsen</label>
        </div>

        <div class="mt4 <?= $showStation ? '' : 'hidden' ?>" data-show-if="a20_3_no_solution_action:self_arranged" data-art="20(3)">
            <div class="small muted">Angiv egne udgifter hvis du selv ordnede transport.</div>
            <div class="grid-2 mt4">
                <label>Transporttype
                    <?php $satype = $v('a20_3_self_arranged_type'); ?>
                    <select name="a20_3_self_arranged_type">
                        <option value="">Vaelg</option>
                        <option value="rail" <?= $satype==='rail'?'selected':'' ?>>Andet tog</option>
                        <option value="bus" <?= $satype==='bus'?'selected':'' ?>>Bus</option>
                        <option value="taxi" <?= $satype==='taxi'?'selected':'' ?>>Taxi/minibus</option>
                        <option value="rideshare" <?= $satype==='rideshare'?'selected':'' ?>>Samkoersel/rideshare</option>
                        <option value="hotel" <?= $satype==='hotel'?'selected':'' ?>>Hotel/overnatning</option>
                        <option value="other" <?= $satype==='other'?'selected':'' ?>>Andet</option>
                    </select>
                </label>
                <label>Beloeb
                    <input type="number" step="0.01" name="a20_3_self_paid_amount" value="<?= h($v('a20_3_self_paid_amount')) ?>" />
                </label>
                <label>Valuta (fx DKK/EUR)
                    <input type="text" name="a20_3_self_paid_currency" value="<?= h($v('a20_3_self_paid_currency')) ?>" />
                </label>
                <label class="small">Kvittering
                    <input type="file" name="a20_3_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
                </label>
            </div>
            <?php if ($f = $v('a20_3_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
        </div>

        <div class="mt8" id="journeyOutcomeWrap">
            <label><?= h($journeyOutcomeLabel) ?>
                <?php $jo = $v('journey_outcome'); ?>
                <select name="journey_outcome" id="journey_outcome">
                    <option value="">Vaelg</option>
                    <option value="continued_trip" <?= $jo==='continued_trip'?'selected':'' ?>>Jeg kom videre (ankom senere)</option>
                    <option value="returned_origin" <?= $jo==='returned_origin'?'selected':'' ?>>Jeg kom tilbage til afgangsstedet</option>
                    <option value="abandoned" <?= $jo==='abandoned'?'selected':'' ?>>Jeg opgav rejsen (kom ikke frem)</option>
                    <option value="self_arranged" <?= $jo==='self_arranged'?'selected':'' ?>>Jeg fandt selv en loesning</option>
                </select>
            </label>
            <div id="journeyOutcomeHint" class="small muted mt4 hidden"><?= h($journeyOutcomeHint) ?></div>
        </div>
    </div>
</div>

<script>
(function(){
    function updateReveal() {
        document.querySelectorAll('[data-show-if]').forEach(function(el) {
            var spec = el.getAttribute('data-show-if'); if (!spec) return;
            var parts = spec.split(':'); if (parts.length !== 2) return;
            var name = parts[0]; var valid = parts[1].split(',');
            var val = '';
            var checked = document.querySelector('input[name="' + name + '"]:checked');
            if (checked) {
                val = checked.value || '';
            } else {
                var sel = document.querySelector('select[name="' + name + '"]');
                if (sel) { val = sel.value || ''; }
            }
            var show = val && valid.includes(val);
            el.style.display = show ? 'block' : 'none';
            el.hidden = !show;
        });
    }
    function clearField(name) {
        document.querySelectorAll('[name="' + name + '"]').forEach(function(el) {
            if (el.type === 'radio' || el.type === 'checkbox') { el.checked = false; return; }
            if (el.tagName === 'SELECT') {
                el.value = '';
                if (el.value !== '') { el.selectedIndex = 0; }
                return;
            }
            el.value = '';
        });
    }
    function clearFields(names) { names.forEach(clearField); }
    function handleResets(target) {
        var name = target.name || '';
        if (name === 'stranded_location') {
            var v = target.value || '';
            if (v === 'track') {
                clearFields([
                    'a20_3_solution_offered','a20_3_solution_type','a20_3_solution_offered_by',
                    'a20_3_no_solution_action','a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt'
                    ,'a20_3_to_destination','a20_3_arrival_station','a20_3_arrival_station_other'
                ]);
            } else if (v === 'station') {
                clearFields([
                    'blocked_train_alt_transport','assistance_alt_transport_type','assistance_alt_to_destination','assistance_alt_transport_offered_by',
                    'blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt',
                    'assistance_alt_arrival_station','assistance_alt_arrival_station_other'
                ]);
            } else {
                clearFields([
                    'blocked_train_alt_transport','assistance_alt_transport_type','assistance_alt_to_destination','assistance_alt_transport_offered_by',
                    'blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt',
                    'a20_3_solution_offered','a20_3_solution_type','a20_3_solution_offered_by',
                    'a20_3_no_solution_action','a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt',
                    'journey_outcome',
                    'assistance_alt_arrival_station','assistance_alt_arrival_station_other',
                    'a20_3_to_destination','a20_3_arrival_station','a20_3_arrival_station_other'
                ]);
            }
            return;
        }
        if (name === 'blocked_train_alt_transport') {
            var v2 = target.value || '';
            if (v2 === 'yes') {
                clearFields(['blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt']);
            } else if (v2 === 'no') {
                clearFields(['assistance_alt_transport_type','assistance_alt_to_destination','assistance_alt_transport_offered_by','assistance_alt_arrival_station','assistance_alt_arrival_station_other']);
            } else {
                clearFields([
                    'blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt',
                    'assistance_alt_transport_type','assistance_alt_to_destination','assistance_alt_transport_offered_by',
                    'assistance_alt_arrival_station','assistance_alt_arrival_station_other'
                ]);
            }
            return;
        }
        if (name === 'blocked_no_transport_action') {
            if ((target.value || '') !== 'self_arranged') {
                clearFields(['blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt']);
            }
            return;
        }
        if (name === 'a20_3_solution_offered') {
            var v3 = target.value || '';
            if (v3 === 'yes') {
                clearFields(['a20_3_no_solution_action','a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt']);
            } else if (v3 === 'no') {
                clearFields(['a20_3_solution_type','a20_3_solution_offered_by','a20_3_to_destination','a20_3_arrival_station','a20_3_arrival_station_other']);
            } else {
                clearFields([
                    'a20_3_solution_type','a20_3_solution_offered_by',
                    'a20_3_no_solution_action','a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt',
                    'a20_3_to_destination','a20_3_arrival_station','a20_3_arrival_station_other'
                ]);
            }
            return;
        }
        if (name === 'a20_3_no_solution_action') {
            if ((target.value || '') !== 'self_arranged') {
                clearFields(['a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt']);
            }
            return;
        }
        if (name === 'assistance_alt_to_destination') {
            var v4 = target.value || '';
            if (!(v4 === 'nearest_station' || v4 === 'other_departure_point')) {
                clearFields(['assistance_alt_arrival_station','assistance_alt_arrival_station_other']);
            }
            return;
        }
        if (name === 'assistance_alt_arrival_station') {
            if ((target.value || '') !== 'other') {
                clearFields(['assistance_alt_arrival_station_other']);
            }
            return;
        }
        if (name === 'a20_3_to_destination') {
            var v5 = target.value || '';
            if (!(v5 === 'nearest_station' || v5 === 'other_departure_point')) {
                clearFields(['a20_3_arrival_station','a20_3_arrival_station_other']);
            }
            return;
        }
        if (name === 'a20_3_arrival_station') {
            if ((target.value || '') !== 'other') {
                clearFields(['a20_3_arrival_station_other']);
            }
            return;
        }
    }
    function updateOutcome(){
        var outcomeEl = document.getElementById('journey_outcome');
        var outcomeWrap = document.getElementById('journeyOutcomeWrap');
        var outcomeHint = document.getElementById('journeyOutcomeHint');
        if (!outcomeEl || !outcomeWrap) { return; }
        var loc = (document.querySelector('input[name="stranded_location"]:checked') || {}).value || '';
        var solOff = (document.querySelector('input[name="a20_3_solution_offered"]:checked') || {}).value || '';
        var noAction = (document.querySelector('input[name="a20_3_no_solution_action"]:checked') || {}).value || '';
        var blkAlt = (document.querySelector('input[name="blocked_train_alt_transport"]:checked') || {}).value || '';
        var blkAction = (document.querySelector('input[name="blocked_no_transport_action"]:checked') || {}).value || '';
        var autoOutcome = '';
        if (loc === 'station' && solOff === 'no' && noAction) {
            if (noAction === 'self_arranged') autoOutcome = 'self_arranged';
            else if (noAction === 'went_home') autoOutcome = 'returned_origin';
            else if (noAction === 'abandoned') autoOutcome = 'abandoned';
        }
        if (loc === 'track' && blkAlt === 'no' && blkAction) {
            if (blkAction === 'self_arranged') autoOutcome = 'self_arranged';
            else if (['waited','walked_station','evacuated_later'].includes(blkAction)) autoOutcome = 'continued_trip';
        }
        var allowOutcome = (loc === 'track' || loc === 'station') && ((loc === 'track' && blkAlt) || (loc === 'station' && solOff));
        if (!allowOutcome) {
            outcomeWrap.style.display = 'none';
            outcomeWrap.hidden = true;
            outcomeEl.disabled = false;
            if (outcomeHint) outcomeHint.classList.add('hidden');
            return;
        }
        outcomeWrap.style.display = '';
        outcomeWrap.hidden = false;
        if (autoOutcome) {
            if (outcomeEl.value !== autoOutcome) { outcomeEl.value = autoOutcome; }
            outcomeEl.disabled = true;
            if (outcomeHint) outcomeHint.classList.remove('hidden');
        } else {
            outcomeEl.disabled = false;
            if (outcomeHint) outcomeHint.classList.add('hidden');
        }
    }
    document.addEventListener('DOMContentLoaded', function(){
        updateReveal();
        updateOutcome();
    });
    document.addEventListener('change', function(e){
        if (!e.target || !e.target.name) return;
        handleResets(e.target);
        updateReveal();
        updateOutcome();
    });
})();
</script>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
    <?= $this->Html->link('Tilbage', ['action' => 'incident'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Fortsaet', ['id' => 'choicesSubmitBtn', 'class' => 'button', 'type' => 'submit', 'aria-label' => 'Fortsaet til naeste trin', 'formnovalidate' => true]) ?>
    <?= $this->Html->link('Spring over', ['controller' => 'Flow', 'action' => 'remedies'], ['class' => 'button', 'style' => 'background:#f5f5f5; color:#333;', 'title' => 'Gaa til naeste trin uden at gemme aendringer']) ?>
    <input type="hidden" name="_choices_submitted" value="1" />
</div>

<?= $this->Form->end() ?>
</div>
