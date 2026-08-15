<?php
/**
 * Right-hand panel for progress and read-only status.
 *
 * Values are expected from CakePHP. No calculations live here.
 */

$compensation = $compensation ?? null;
$compRate = $compRate ?? null;
$compBase = $compBase ?? null;
$progressPct = $progressPct ?? 0;
$progressLabel = $progressLabel ?? '';
$stats = $stats ?? [];
$summaryRows = $summaryRows ?? [];
$nextHint = $nextHint ?? 'Udfyld trinnet og fortsaet.';
$liveEstimateHtml = $liveEstimateHtml ?? '';
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$profile = is_array($profile ?? null) ? (array)$profile : [];
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$panelTranslations = [
    'fr' => [
        'Udfyld trinnet og fortsaet.' => 'Remplissez cette etape et poursuivez.',
        'Aktiv' => 'Actif',
        'Afventer' => 'En attente',
        'Beregnes loebende ud fra dine svar' => 'Calcule en continu a partir de vos reponses',
        'Kompensation' => 'Indemnisation',
        'Naeste' => 'Suite',
        'Afventer haendelse' => 'En attente de l incident',
        'Afventer flere svar' => 'En attente de davantage de reponses',
        'Afventer svar' => 'En attente de reponse',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Operatoer' => 'Operateur',
        'Tilbagebetaling' => 'Remboursement',
        'Fuld daekning mulig' => 'Couverture complete possible',
        'Rimelige noedvendige udgifter kan daekkes' => 'Les frais necessaires raisonnables peuvent etre couverts',
        'Foreloebigt kompensationsniveau' => 'Niveau d indemnisation provisoire',
        'Kompensation mulig' => 'Indemnisation possible',
        'Ikke aktiveret endnu' => 'Pas encore active',
        'Kompensation blokeret' => 'Indemnisation bloquee',
        'Omlaegning / assistance aktiv' => 'Reacheminement / assistance actifs',
        'Under kompensationstaerskel' => 'Sous le seuil d indemnisation',
        'Ikke valgt endnu' => 'Pas encore selectionne',
    ],
];
$translatePanel = static function ($text) use ($uiLanguage, $panelTranslations) {
    if (!is_string($text)) {
        return $text;
    }

    if (isset($panelTranslations[$uiLanguage][$text])) {
        return $panelTranslations[$uiLanguage][$text];
    }

    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $panelTranslations[$uiLanguage][$decoded] ?? $text;
};
$nextHint = $translatePanel($nextHint);

if ($form === [] || $flags === [] || $meta === []) {
    $session = $this->getRequest()->getSession();
    $form = $form ?: (array)$session->read('flow.form');
    $flags = $flags ?: (array)$session->read('flow.flags');
    $meta = $meta ?: (array)$session->read('flow.meta');
    $journey = $journey ?: (array)$session->read('flow.journey');
    if ($multimodal === []) {
        $multimodal = (array)($meta['_multimodal'] ?? []);
    }
}

$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['gating_mode'] ?? ($meta['transport_mode'] ?? ''))));
if ($liveEstimateHtml === '' && in_array($transportMode, ['rail', 'air', 'ferry'], true)) {
    ob_start();
    if ($transportMode === 'rail') {
        echo $this->element('rail_live_estimate', compact('form', 'flags', 'meta', 'journey', 'profile'));
    } elseif ($transportMode === 'air') {
        $airRights = (array)($multimodal['air_rights'] ?? []);
        $airScope = (array)($multimodal['air_scope'] ?? []);
        $airContract = (array)($multimodal['air_contract'] ?? []);
        echo $this->element('air_live_estimate', compact('form', 'flags', 'meta', 'airRights', 'airScope', 'airContract'));
    } elseif ($transportMode === 'ferry') {
        $ferryRights = (array)($multimodal['ferry_rights'] ?? []);
        $ferryScope = (array)($multimodal['ferry_scope'] ?? []);
        echo $this->element('ferry_live_estimate', compact('form', 'flags', 'meta', 'journey', 'ferryRights', 'ferryScope'));
    }
    $liveEstimateHtml = trim((string)ob_get_clean());
}
if ($liveEstimateHtml !== '' && $uiLanguage !== 'da' && isset($panelTranslations[$uiLanguage])) {
    $liveEstimateHtml = strtr($liveEstimateHtml, $panelTranslations[$uiLanguage]);
}

$hasLiveEstimate = $liveEstimateHtml !== '';
$liveEstimateTone = 'gray';
if ($hasLiveEstimate && preg_match('/data-live-tone="(gray|green|red)"/i', $liveEstimateHtml, $matches)) {
    $liveEstimateTone = strtolower((string)$matches[1]);
}
$compValue = is_numeric($compensation) ? (float)$compensation : 0.0;
$hasCompAmount = $compValue > 0;
$compStatusLabel = $translatePanel($hasCompAmount ? 'Aktiv' : 'Afventer');
$compStatusClass = $hasCompAmount ? 'green' : 'gray';
$compAmountLabel = $hasCompAmount ? rtrim(rtrim(number_format($compValue, 2, '.', ''), '0'), '.') . ' kr.' : $translatePanel('Afventer');
$compMetaLabel = ($compRate && $compBase)
    ? ((string)$compRate . ' af ' . (string)$compBase)
    : $translatePanel('Beregnes loebende ud fra dine svar');
?>
<div class="tc6-right-panel">
  <?php if ($hasLiveEstimate): ?>
    <div class="tc6-panel-live-estimate tc6-panel-live-estimate--<?= h($liveEstimateTone) ?>" data-live-tone="<?= h($liveEstimateTone) ?>"><?= $liveEstimateHtml ?></div>
  <?php endif; ?>
  <?php if (!$hasLiveEstimate): ?>
    <div class="tc6-panel-card tc6-panel-card--summary">
      <div class="tc6-panel-summary-head">
        <div>
          <div class="tc6-panel-label"><?= h($translatePanel('Kompensation')) ?></div>
          <div class="tc6-comp-amount <?= $hasCompAmount ? '' : 'tc6-comp-amount--zero' ?>">
            <?= h($compAmountLabel) ?>
          </div>
        </div>
        <span class="tc6-badge tc6-badge--<?= h($compStatusClass) ?>"><?= h($compStatusLabel) ?></span>
      </div>
      <div class="tc6-panel-summary-meta"><?= h($compMetaLabel) ?></div>

      <div class="tc6-progress-track">
        <div class="tc6-progress-fill" style="width:<?= (int)$progressPct ?>%"></div>
      </div>
      <div class="tc6-panel-summary-progress"><?= h($progressLabel) ?></div>

      <?php if (!empty($summaryRows)): ?>
        <div class="tc6-panel-summary-table">
          <?php foreach ($summaryRows as $row):
              [$key, $val, $badge] = array_pad($row, 3, null);
          ?>
            <div class="tc6-panel-summary-row">
              <span class="tc6-panel-summary-key"><?= h($key) ?></span>
              <?php if ($badge): ?>
                <span class="tc6-badge tc6-badge--<?= h($badge) ?>"><?= h($val) ?></span>
              <?php else: ?>
                <span class="tc6-panel-summary-val"><?= h($val) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!$hasLiveEstimate): ?>
    <div class="tc6-panel-card">
      <div class="tc6-panel-label"><?= h($translatePanel('Naeste')) ?></div>
      <div style="font-size:12px;color:var(--tc-text-2);line-height:1.6">
        <?= h($nextHint) ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php if ($hasLiveEstimate): ?>
<script>
(function () {
  function textOf(selectorOrElement) {
    var node = typeof selectorOrElement === 'string'
      ? document.querySelector(selectorOrElement)
      : selectorOrElement;
    return node ? String(node.textContent || '').trim() : '';
  }

  function hasText(selectorOrElement, expected) {
    return textOf(selectorOrElement).toLowerCase().indexOf(String(expected).toLowerCase()) !== -1;
  }

  function setTone(panel, tone) {
    if (!panel) {
      return;
    }
    var normalized = tone === 'green' || tone === 'red' ? tone : 'gray';
    var wrapper = panel.closest('.tc6-panel-live-estimate');
    panel.dataset.liveTone = normalized;
    ['gray', 'green', 'red'].forEach(function (candidate) {
      panel.classList.toggle('tc6-live-estimate--' + candidate, candidate === normalized);
      if (wrapper) {
        wrapper.classList.toggle('tc6-panel-live-estimate--' + candidate, candidate === normalized);
      }
    });
    if (wrapper) {
      wrapper.dataset.liveTone = normalized;
    }
  }

  function setStateTone(node, baseClass, tone) {
    if (!node) {
      return;
    }
    var normalized = tone === 'green' || tone === 'red' ? tone : 'gray';
    ['gray', 'green', 'red'].forEach(function (candidate) {
      node.classList.toggle(baseClass + '--' + candidate, candidate === normalized);
    });
    node.dataset.liveTone = normalized;
  }

  function applyChipTone(node, tone) {
    if (!node) {
      return;
    }
    var normalized = tone === 'green' || tone === 'red' ? tone : 'gray';
    if (normalized === 'green') {
      node.style.background = 'rgba(220,252,231,0.98)';
      node.style.borderColor = 'rgba(34,197,94,0.34)';
      node.style.color = '#166534';
      return;
    }
    if (normalized === 'red') {
      node.style.background = 'rgba(254,226,226,0.98)';
      node.style.borderColor = 'rgba(239,68,68,0.34)';
      node.style.color = '#b91c1c';
      return;
    }
    node.style.background = 'rgba(248,250,252,0.96)';
    node.style.borderColor = 'rgba(148,163,184,0.30)';
    node.style.color = '#475569';
  }

  function applyCardTone(node, tone) {
    if (!node) {
      return;
    }
    var normalized = tone === 'green' || tone === 'red' ? tone : 'gray';
    if (normalized === 'green') {
      node.style.background = 'rgba(240,253,244,0.98)';
      node.style.borderColor = 'rgba(34,197,94,0.28)';
      return;
    }
    if (normalized === 'red') {
      node.style.background = 'rgba(254,242,242,0.98)';
      node.style.borderColor = 'rgba(239,68,68,0.28)';
      return;
    }
    node.style.background = 'rgba(248,250,252,0.96)';
    node.style.borderColor = 'rgba(148,163,184,0.22)';
  }

  function updateAirTone() {
    var panel = document.getElementById('airLiveEstimate');
    if (!panel) {
      return;
    }
    var careActive = hasText('#airLiveEstimateCareValue', 'kan daekkes') || hasText('#airLiveEstimateCareValue', 'peuvent etre couverts');
    var remedyActive = hasText('#airLiveEstimateRemedyValue', 'Fuld daekning mulig') || hasText('#airLiveEstimateRemedyValue', 'Couverture complete possible');
    var statusText = textOf('#airLiveEstimateStatus');
    var compensationPositive = /Kompensation mulig|Indemnisation possible|50% reduktion/i.test(statusText);
    var compensationNegative = /Ingen kompensation|Ikke aktiveret endnu|Pas encore active/i.test(statusText);
    var carrierValue = textOf('[data-air-live-card="carrier"] .air-live-estimate-value');
    var carrierKnown = carrierValue !== '' && !/Afventer svar|En attente de reponse/i.test(carrierValue);
    var statusTone = compensationPositive ? 'green' : (compensationNegative ? 'red' : 'gray');

    setTone(panel, statusTone);
    setStateTone(panel.querySelector('#airLiveEstimateStatus'), 'tc6-live-chip', statusTone);
    applyChipTone(panel.querySelector('#airLiveEstimateStatus'), statusTone);
    setStateTone(panel.querySelector('[data-air-live-card="care"]'), 'tc6-live-card', careActive ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-air-live-card="care"]'), careActive ? 'green' : 'gray');
    setStateTone(panel.querySelector('[data-air-live-card="remedy"]'), 'tc6-live-card', remedyActive ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-air-live-card="remedy"]'), remedyActive ? 'green' : 'gray');
    setStateTone(panel.querySelector('[data-air-live-card="carrier"]'), 'tc6-live-card', carrierKnown ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-air-live-card="carrier"]'), carrierKnown ? 'green' : 'gray');
  }

  function updateFerryTone() {
    var panel = document.getElementById('ferryLiveEstimate');
    if (!panel) {
      return;
    }
    var assistanceActive = hasText('[data-ferry-live-art17]', 'kan daekkes') || hasText('[data-ferry-live-art17]', 'peuvent etre couverts');
    var remedyActive = hasText('[data-ferry-live-art18]', 'Fuld daekning mulig') || hasText('[data-ferry-live-art18]', 'Couverture complete possible');
    var compensationActive = /Art\. 19 aktiv/i.test(textOf('[data-ferry-live-status]')) || /%/.test(textOf('[data-ferry-live-art19]'));
    var resolvedNegative = /Under kompensationst(?:ae|æ)rskel/i.test(textOf('[data-ferry-live-status]'));
    var operatorValue = textOf('[data-ferry-live-card="operator"] .ferry-live-estimate-value');
    var operatorKnown = operatorValue !== '' && !/Ikke valgt endnu|Pas encore selectionne|En attente de reponse/i.test(operatorValue);
    var statusTone = compensationActive ? 'green' : (resolvedNegative ? 'red' : 'gray');

    setTone(panel, statusTone);
    setStateTone(panel.querySelector('[data-ferry-live-status]'), 'tc6-live-chip', statusTone);
    applyChipTone(panel.querySelector('[data-ferry-live-status]'), statusTone);
    setStateTone(panel.querySelector('[data-ferry-live-card="assistance"]'), 'tc6-live-card', assistanceActive ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-ferry-live-card="assistance"]'), assistanceActive ? 'green' : 'gray');
    setStateTone(panel.querySelector('[data-ferry-live-card="remedy"]'), 'tc6-live-card', remedyActive ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-ferry-live-card="remedy"]'), remedyActive ? 'green' : 'gray');
    setStateTone(panel.querySelector('[data-ferry-live-card="operator"]'), 'tc6-live-card', operatorKnown ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-ferry-live-card="operator"]'), operatorKnown ? 'green' : 'gray');
  }

  function updateRailTone() {
    var panel = document.getElementById('railLiveEstimate');
    if (!panel) {
      return;
    }
    var assistanceActive = hasText('[data-rail-live-art20]', 'kan daekkes') || hasText('[data-rail-live-art20]', 'peuvent etre couverts');
    var remedyActive = hasText('[data-rail-live-art18]', 'Fuld daekning mulig') || hasText('[data-rail-live-art18]', 'Couverture complete possible');
    var statusText = textOf('[data-rail-live-status]');
    var thresholdText = textOf('[data-rail-live-threshold]');
    var compensationActive = /Foreloebigt kompensationsniveau|Niveau d indemnisation provisoire/i.test(statusText);
    var resolvedNegative = /Kompensation blokeret/i.test(statusText) || /Under 60 min/i.test(thresholdText);
    var operatorValue = textOf('[data-rail-live-card="operator"] .rail-live-estimate-value');
    var operatorKnown = operatorValue !== '' && !/Afventer svar|En attente de reponse|Ikke valgt endnu|Pas encore selectionne/i.test(operatorValue);
    var statusTone = compensationActive ? 'green' : (resolvedNegative ? 'red' : 'gray');

    setTone(panel, statusTone);
    setStateTone(panel.querySelector('[data-rail-live-status]'), 'tc6-live-chip', statusTone);
    applyChipTone(panel.querySelector('[data-rail-live-status]'), statusTone);
    setStateTone(panel.querySelector('[data-rail-live-card="remedy"]'), 'tc6-live-card', remedyActive ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-rail-live-card="remedy"]'), remedyActive ? 'green' : 'gray');
    setStateTone(panel.querySelector('[data-rail-live-card="assistance"]'), 'tc6-live-card', assistanceActive ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-rail-live-card="assistance"]'), assistanceActive ? 'green' : 'gray');
    setStateTone(panel.querySelector('[data-rail-live-card="operator"]'), 'tc6-live-card', operatorKnown ? 'green' : 'gray');
    applyCardTone(panel.querySelector('[data-rail-live-card="operator"]'), operatorKnown ? 'green' : 'gray');
  }

  function refreshAllTones() {
    updateAirTone();
    updateFerryTone();
    updateRailTone();
  }

  function observePanel(panel) {
    if (!panel || panel.dataset.tc6ToneObserved === '1') {
      return;
    }
    panel.dataset.tc6ToneObserved = '1';
    var observer = new MutationObserver(function () {
      refreshAllTones();
    });
    observer.observe(panel, {
      subtree: true,
      childList: true,
      characterData: true
    });
  }

  function bootToneObservers() {
    refreshAllTones();
    observePanel(document.getElementById('airLiveEstimate'));
    observePanel(document.getElementById('ferryLiveEstimate'));
    observePanel(document.getElementById('railLiveEstimate'));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootToneObservers);
  } else {
    bootToneObservers();
  }

  document.addEventListener('change', refreshAllTones, true);
  document.addEventListener('input', refreshAllTones, true);
})();
</script>
<?php endif; ?>
