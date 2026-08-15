<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$routeType = $routeType ?? 'direct';
$routeLegs = $routeLegs ?? [];
$sellerChannel = $sellerChannel ?? 'operator';
$bookingTopology = $bookingTopology ?? '';
$problemContractId = $problemContractId ?? '';
$contractUnits = $contractUnits ?? [];
$problemLegId = $problemLegId ?? '';
$isPreview = !empty($flowPreview);
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$pageTranslations = (array)($pageTranslations ?? []);

if ($uiLanguage === 'fr') {
    $pageTranslations += [
        'TRIN' => 'ETAPE',
        'Reservation, kontrakt' => 'Reservation, contrat',
        '1. Koebssted' => '1. Lieu d achat',
        'Hvem koebte reservationen hos?' => 'Ou la reservation a-t-elle ete achetee ?',
        'Direkte hos flyselskab' => 'Directement aupres de la compagnie aerienne',
        'Rejsebureau' => 'Agence de voyages',
        'Billetudbyder' => 'Vendeur de billets',
        'Flere steder' => 'Plusieurs endroits',
        '2. Bookingstruktur' => '2. Structure de reservation',
        'Var flyvningerne booket samlet?' => 'Les vols ont-ils ete reserves ensemble ?',
        'Samlet booking / samme PNR' => 'Reservation unique / meme PNR',
        'Separate billetter / self-transfer' => 'Billets separes / self-transfer',
        '3. Relevant reservation' => '3. Reservation concernee',
        'Hvilken reservation/kontrakt vedroerer kravet?' => 'Quelle reservation / quel contrat concerne la reclamation ?',
        'Bookingreference:' => 'Reference de reservation :',
        'Reservation ' => 'Reservation ',
        '3. Problemafgang' => '3. Vol concerne',
        'Hvor opstod problemet i reservationen?' => 'Ou le probleme est-il survenu dans la reservation ?',
        'Tilbage' => 'Retour',
        'Naeste' => 'Suivant',
        'Naeste trin matcher den konkrete flyvning for den valgte afgang.' => 'L etape suivante identifie le vol precis pour le depart choisi.',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$stepHeading = 'TRIN 3';
$stepTitle = 'Reservation, kontrakt';
foreach (($flowSteps ?? []) as $step) {
    if ((string)($step['action'] ?? '') !== 'airReservationContract') {
        continue;
    }
    $stepNum = $step['ui_num'] ?? $step['num'] ?? 3;
    $stepHeading = 'TRIN ' . (string)$stepNum;
    $stepTitle = (string)($step['title'] ?? $stepTitle);
    break;
}

$routePoints = [];
foreach ($routeLegs as $index => $leg) {
    if (!is_array($leg)) {
        continue;
    }
    $depLabel = trim((string)($leg['dep_label'] ?? ''));
    $arrLabel = trim((string)($leg['arr_label'] ?? ''));
    if ($index === 0 && $depLabel !== '') {
        $routePoints[] = $depLabel;
    }
    if ($arrLabel !== '') {
        $routePoints[] = $arrLabel;
    }
}
$routeLine = implode(' -> ', $routePoints);
$ticketReference = trim((string)($form['ticket_no'] ?? ($meta['_identifiers']['pnr'] ?? '')));
?>
<?php echo $this->Html->css('flow-select-steps', ['block' => true]); ?>
<?php echo $this->Html->css('flow-form-steps', ['block' => true]); ?>

<style>
  .arc-step { max-width: 1040px; }
  .arc-form-card { padding:18px 22px; border:1px solid #dbe3ea; background:#fff; border-radius:24px; box-shadow:0 8px 20px rgba(15, 23, 42, .04); }
  .arc-section + .arc-section { border-top:1px solid #e2e8f0; margin-top:22px; padding-top:22px; }
  .arc-section-title { font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.12em; }
  .arc-question { margin-top:10px; font-size:18px; font-weight:800; color:#0f172a; }
  .arc-inline-options { display:flex; gap:18px; flex-wrap:wrap; margin-top:14px; }
  .arc-inline-option { display:inline-flex; align-items:center; gap:10px; color:#0f172a; font-family:"DM Sans", "Segoe UI", sans-serif; font-size:16px; font-weight:400; line-height:1.4; }
  .arc-inline-option input[type=radio] { margin:0; }
  .arc-inline-option span { font-weight:400; }
  .arc-choice-list { display:grid; gap:12px; margin-top:14px; }
  .arc-choice-row { display:flex; align-items:flex-start; gap:12px; color:#0f172a; }
  .arc-choice-row input[type=radio] { margin-top:3px; }
  .arc-choice-row label { cursor:pointer; color:#0f172a; font-family:"DM Sans", "Segoe UI", sans-serif; font-weight:400; line-height:1.4; }
  .arc-choice-title { display:block; font-size:16px; font-weight:400; }
  .arc-choice-meta { display:block; margin-top:4px; color:#64748b; font-size:14px; }
  .arc-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .button-secondary { display:inline-block; padding:12px 16px; border:1px solid #cbd5e1; border-radius:12px; background:#fff; color:#0f172a; font-weight:700; text-decoration:none; }
  .button-secondary:hover { background:#f8fafc; border-color:#94a3b8; }
  .button-primary { display:inline-block; padding:12px 16px; border:none; border-radius:12px; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; }
  .button-primary:hover { background:#1e293b; }
  .mt12 { margin-top:12px; }
  .mt16 { margin-top:16px; }
  .small { font-size:12px; }
  .muted { color:#64748b; }
</style>

<div class="arc-step ffs-shell">
  <div class="ffs-step"><?= h($stepHeading) ?></div>
  <h1 class="ffs-title"><?= h($stepTitle) ?></h1>

  <?= $this->element('flow_locked_notice') ?>
  <?= $this->Form->create(null, ['novalidate' => true]) ?>
  <fieldset <?= $isPreview ? 'disabled' : '' ?>>
    <section class="arc-form-card mt16">
      <div class="arc-section">
        <div class="arc-section-title">1. Koebssted</div>
        <div class="arc-question">Hvem koebte reservationen hos?</div>
        <div class="arc-inline-options">
          <?php foreach ([
              'operator' => 'Direkte hos flyselskab',
              'agency' => 'Rejsebureau',
              'retailer' => 'Billetudbyder',
              'tour_operator' => 'Flere steder',
          ] as $value => $title): ?>
            <?php $checked = $sellerChannel === $value; ?>
            <label class="arc-inline-option" for="seller_<?= h($value) ?>">
              <input id="seller_<?= h($value) ?>" type="radio" name="seller_channel" value="<?= h($value) ?>" <?= $checked ? 'checked' : '' ?> />
              <span><?= h($title) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($routeType === 'connecting'): ?>
        <div class="arc-section">
          <div class="arc-section-title">2. Bookingstruktur</div>
          <div class="arc-question">Var flyvningerne booket samlet?</div>
          <div class="arc-inline-options">
            <?php foreach ([
                'through_booking' => 'Samlet booking / samme PNR',
                'separate_contracts' => 'Separate billetter / self-transfer',
            ] as $value => $title): ?>
              <?php $checked = $bookingTopology === $value; ?>
              <label class="arc-inline-option" for="topology_<?= h($value) ?>">
                <input id="topology_<?= h($value) ?>" type="radio" name="air_booking_topology_answer" value="<?= h($value) ?>" <?= $checked ? 'checked' : '' ?> />
                <span><?= h($title) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="arc-section" id="contractScopeBlock" style="<?= $bookingTopology === 'separate_contracts' ? '' : 'display:none;' ?>">
          <div class="arc-section-title">3. Relevant reservation</div>
          <div class="arc-question">Hvilken reservation/kontrakt vedroerer kravet?</div>
          <?php if ($ticketReference !== ''): ?>
            <div class="arc-choice-meta mt12">Bookingreference: <?= h($ticketReference) ?></div>
          <?php endif; ?>
          <div class="arc-choice-list" id="contractChoiceList">
            <?php foreach ($contractUnits as $index => $unit): ?>
              <?php
                $unitId = (string)($unit['id'] ?? ('contract_' . $index));
                $checked = $problemContractId === $unitId;
                $unitRoute = trim((string)($unit['origin'] ?? '')) . ' -> ' . trim((string)($unit['destination'] ?? ''));
              ?>
              <div class="arc-choice-row">
                <input id="contract_<?= h($unitId) ?>" type="radio" name="air_problem_contract_id" value="<?= h($unitId) ?>" <?= $checked ? 'checked' : '' ?> />
                <label for="contract_<?= h($unitId) ?>">
                  <span class="arc-choice-title">Reservation <?= h((string)($index + 1)) ?></span>
                  <span class="arc-choice-meta"><?= h($unitRoute) ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="arc-section" id="problemLegBlock" style="<?= $bookingTopology === 'through_booking' ? '' : 'display:none;' ?>">
          <div class="arc-section-title">3. Problemafgang</div>
          <div class="arc-question">Hvor opstod problemet i reservationen?</div>
          <div class="arc-choice-list" id="problemLegChoiceList">
            <?php foreach ($routeLegs as $index => $leg): ?>
              <?php
                if (!is_array($leg)) {
                    continue;
                }
                $legKey = (string)($leg['key'] ?? ('leg_' . $index));
                $checked = $problemLegId === $legKey;
                $legRoute = trim((string)($leg['dep_label'] ?? '')) . ' -> ' . trim((string)($leg['arr_label'] ?? ''));
              ?>
              <div class="arc-choice-row">
                <input id="problem_leg_<?= h($legKey) ?>" type="radio" name="air_disruption_leg_id" value="<?= h($legKey) ?>" <?= $checked ? 'checked' : '' ?> />
                <label for="problem_leg_<?= h($legKey) ?>">
                  <span class="arc-choice-title"><?= h($legRoute) ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <input type="hidden" name="air_booking_topology_answer" value="" />
      <?php endif; ?>
    </section>

    <div class="arc-actions mt16">
      <?= $this->Html->link('Tilbage', ['action' => 'entitlements'], ['class' => 'button-secondary']) ?>
      <button type="submit" class="button-primary">Naeste</button>
      <span class="small muted">Naeste trin matcher den konkrete flyvning for den valgte afgang.</span>
    </div>
  </fieldset>
  <?= $this->Form->end() ?>
  <?= $this->element('flow_autosave', ['step' => 'air_reservation_contract']) ?>
</div>

<script>
(() => {
  const routeLegs = <?= json_encode(array_values(array_filter($routeLegs, 'is_array')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const topologyInputs = Array.from(document.querySelectorAll('input[name="air_booking_topology_answer"]'));
  const contractScopeBlock = document.getElementById('contractScopeBlock');
  const contractChoiceList = document.getElementById('contractChoiceList');
  const problemLegBlock = document.getElementById('problemLegBlock');
  const problemLegChoiceList = document.getElementById('problemLegChoiceList');

  const selectedProblemContractId = () => {
    const checked = document.querySelector('input[name="air_problem_contract_id"]:checked');
    return checked ? String(checked.value || '') : '';
  };

  const selectedProblemLegId = () => {
    const checked = document.querySelector('input[name="air_disruption_leg_id"]:checked');
    return checked ? String(checked.value || '') : '';
  };

  const renderContractChoices = () => {
    if (!contractChoiceList) {
      return;
    }
    const selectedId = selectedProblemContractId();
    contractChoiceList.innerHTML = '';
    routeLegs.forEach((leg, index) => {
      const legKey = String(leg.key || ('leg_' + (index + 1)));
      const depLabel = String(leg.dep_label || '').trim();
      const arrLabel = String(leg.arr_label || '').trim();
      const row = document.createElement('div');
      row.className = 'arc-choice-row';

      const input = document.createElement('input');
      input.type = 'radio';
      input.name = 'air_problem_contract_id';
      input.id = 'contract_' + legKey;
      input.value = legKey;
      input.checked = selectedId !== '' && selectedId === legKey;

      const label = document.createElement('label');
      label.setAttribute('for', input.id);

      const title = document.createElement('span');
      title.className = 'arc-choice-title';
      title.textContent = 'Reservation ' + String(index + 1);

      const meta = document.createElement('span');
      meta.className = 'arc-choice-meta';
      meta.textContent = depLabel + ' -> ' + arrLabel;

      label.appendChild(title);
      label.appendChild(meta);
      row.appendChild(input);
      row.appendChild(label);
      contractChoiceList.appendChild(row);
    });
  };

  const renderProblemLegChoices = () => {
    if (!problemLegChoiceList) {
      return;
    }
    const selectedId = selectedProblemLegId();
    problemLegChoiceList.innerHTML = '';
    routeLegs.forEach((leg, index) => {
      const legKey = String(leg.key || ('leg_' + (index + 1)));
      const depLabel = String(leg.dep_label || '').trim();
      const arrLabel = String(leg.arr_label || '').trim();
      const row = document.createElement('div');
      row.className = 'arc-choice-row';

      const input = document.createElement('input');
      input.type = 'radio';
      input.name = 'air_disruption_leg_id';
      input.id = 'problem_leg_' + legKey;
      input.value = legKey;
      input.checked = selectedId !== '' && selectedId === legKey;

      const label = document.createElement('label');
      label.setAttribute('for', input.id);

      const title = document.createElement('span');
      title.className = 'arc-choice-title';
      title.textContent = depLabel + ' -> ' + arrLabel;

      label.appendChild(title);
      row.appendChild(input);
      row.appendChild(label);
      problemLegChoiceList.appendChild(row);
    });
  };

  const syncStepBlocks = () => {
    if (!contractScopeBlock && !problemLegBlock) {
      return;
    }
    const checked = topologyInputs.find((input) => input.checked);
    const showContracts = !!checked && checked.value === 'separate_contracts';
    const showProblemLegs = !!checked && checked.value === 'through_booking';

    if (contractScopeBlock) {
      contractScopeBlock.style.display = showContracts ? '' : 'none';
    }
    if (problemLegBlock) {
      problemLegBlock.style.display = showProblemLegs ? '' : 'none';
    }

    if (showContracts) {
      renderContractChoices();
    }
    if (showProblemLegs) {
      renderProblemLegChoices();
    }

    if (!showContracts && contractScopeBlock) {
      contractScopeBlock.querySelectorAll('input[name="air_problem_contract_id"]').forEach((input) => {
        input.checked = false;
      });
    }
    if (!showProblemLegs && problemLegBlock) {
      problemLegBlock.querySelectorAll('input[name="air_disruption_leg_id"]').forEach((input) => {
        input.checked = false;
      });
    }
  };

  topologyInputs.forEach((input) => {
    input.addEventListener('change', syncStepBlocks, { passive: true });
  });

  syncStepBlocks();
})();
</script>
