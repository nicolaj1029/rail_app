<?php
/**
 * TC6 view for FlowController::downgrade().
 *
 * Thin CakePHP view layer over the existing downgrade step.
 * No progression or calculations are moved out of the controller.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$journey = $journey ?? [];
$metaView = $metaView ?? null;
$meta = is_array($metaView) ? $metaView : ($meta ?? []);
$profile = $profile ?? ['articles' => []];
$journeyRowsDowng = $journeyRowsDowng ?? [];
$affectedLegsAuto = $affectedLegsAuto ?? [];
$downgradeScopeAuto = $downgradeScopeAuto ?? ['from' => null, 'to' => null, 'basis' => '', 'confidence' => 0.0];
$multimodal = (array)($meta['_multimodal'] ?? []);
$airScope = (array)($multimodal['air_scope'] ?? []);
$airContractSeed = (array)($meta['air_contract_structure_seed'] ?? []);
$airBookingSeed = (array)($meta['air_booking_topology_seed'] ?? []);
$airTopology = strtolower(trim((string)($airContractSeed['topology'] ?? ($airBookingSeed['topology'] ?? ''))));
$downgradeTicketOptions = $downgradeTicketOptions ?? [];
$downgradeTicketFile = (string)($downgradeTicketFile ?? ($form['downgrade_ticket_file'] ?? ''));
$airDowngradeRejectedAlert = trim((string)($airDowngradeRejectedAlert ?? ''));
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$translations = [
    'fr' => [
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Trin ' => 'Etape ',
        'Start & Rejsestatus' => 'Depart et statut du voyage',
        'Billet / Ticketless + pris' => 'Billet / sans billet + prix',
        'Rejseoplysninger' => 'Informations de voyage',
        'Vaelg afgang + rail-vurdering' => 'Choix du depart + evaluation rail',
        'Billet, grunddata' => 'Billet, donnees de base',
        'Vaelg fly' => 'Choix du vol',
        'Haendelse' => 'Incident',
        'Valg efter haendelsen' => 'Choix apres l incident',
        'Refund, ombooking' => 'Remboursement, reacheminement',
        'Refusion / Omlaegning' => 'Remboursement / reacheminement',
        'Mad og Hotel' => 'Repas et hotel',
        'Nedgradering' => 'Declassement',
        'Nedgradering: Klasse/Reservation (Annex II)' => 'Declassement : classe / reservation (annexe II)',
        'Kompensation' => 'Indemnisation',
        'Kontakt & opret sag' => 'Contact et creation du dossier',
        'Igangvaerende rejse' => 'Voyage en cours',
        'Afsluttet rejse' => 'Voyage termine',
        'Registrer kun dette trin, hvis du faktisk blev placeret i lavere kabineklasse end koebt.' => 'Renseignez cette etape uniquement si vous avez effectivement ete place dans une classe de cabine inferieure a celle achetee.',
        'Registrer kun dette trin, hvis du faktisk blev placeret i lavere klasse eller mistede en reservation.' => 'Renseignez cette etape uniquement si vous avez effectivement ete place dans une classe inferieure ou si votre reservation n a pas ete honoree.',
        'Registrer kun dette trin, hvis den leverede service var ringere end det, du betalte for.' => 'Renseignez cette etape uniquement si le service fourni etait inferieur a celui que vous aviez paye.',
        'Aktiv' => 'Actif',
        'Ikke aktiv' => 'Inactif',
        'Saede' => 'Siege',
        'Ligge' => 'Couchette',
        'Sove' => 'Lit',
        'Afventer' => 'En attente',
        'Kabineklasse' => 'Classe de cabine',
        'Klasse / reservation' => 'Classe / reservation',
        'Grundlag' => 'Base',
        'Klasse tjekkes' => 'Classe a verifier',
        'Tjekkes i tabel' => 'A verifier dans le tableau',
        'Billet' => 'Billet',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Nedgradering kan vaere begraenset af profil/exemptions i denne sag. Visningen her er kun et tyndt tc6-lag; den endelige vurdering ligger stadig i CakePHP.' => 'Le declassement peut etre limite par le profil / les exemptions dans ce dossier. Cet affichage n est qu une fine couche TC6 ; l evaluation finale reste dans CakePHP.',
        'Billetvalg' => 'Choix du billet',
        'Hvilken billet udfylder du nedgradering for?' => 'Pour quel billet renseignez-vous le declassement ?',
        'Blev du nedgraderet?' => 'Avez-vous ete declasse ?',
        'Ja' => 'Oui',
        'Nej' => 'Non',
        'Du blev placeret i en lavere kabineklasse end den, du havde koebt.' => 'Vous avez ete place dans une classe de cabine inferieure a celle achetee.',
        'Du blev placeret i lavere klasse, eller din reservation blev ikke leveret som koebt.' => 'Vous avez ete place dans une classe inferieure, ou votre reservation n a pas ete fournie comme achetee.',
        'Det her trin er ikke relevant for den konkrete rejse.' => 'Cette etape n est pas pertinente pour ce voyage.',
        'Basis (CIV / Bilag II)' => 'Base (CIV / annexe II)',
        'Vaelg' => 'Choisir',
        'Saede (1 -> 2 klasse)' => 'Siege (1re -> 2e classe)',
        'Ligge (komforttrin ned)' => 'Couchette (niveau de confort inferieur)',
        'Sove (komforttrin ned)' => 'Lit (niveau de confort inferieur)',
        'Andel af rejsen (0-1)' => 'Part du voyage (0-1)',
        'Per-flight dokumentation' => 'Documentation par vol',
        'Per-leg dokumentation' => 'Documentation par segment',
        'Relevant billetpris for nedgradering' => 'Prix du billet pertinent pour le declassement',
        'Kender du billetprisen for den relevante flyvning eller billet?' => 'Connaissez-vous le prix du billet pour le vol ou billet pertinent ?',
        'Prisgrundlag' => 'Base de prix',
        'Pris for markerede downgradede ben paa den relevante billet' => 'Prix des segments declines marques sur le billet pertinent',
        'Pris for markerede downgradede ben' => 'Prix des segments declines marques',
        'Pris for hele den relevante billet' => 'Prix de l ensemble du billet pertinent',
        'Pris for hele billetten' => 'Prix de l ensemble du billet',
        'Ved ikke' => 'Je ne sais pas',
        'Billetpris' => 'Prix du billet',
        'Valuta' => 'Devise',
        'LLM/OCR har udfyldt koebt/leveret niveau; marker nedgraderet hvis leveret var lavere.' => 'LLM/OCR a renseigne le niveau achete / fourni ; marquez declasse si le niveau fourni etait inferieur.',
        'Afgang' => 'Depart',
        'Ankomst' => 'Arrivee',
        'Tog' => 'Train',
        'Koebt klasse' => 'Classe achetee',
        'Koebt reservation' => 'Reservation achetee',
        'Koebt kabineklasse' => 'Classe de cabine achetee',
        'Choisir koebt niveau' => 'Choisir le niveau achete',
        'Choisir koebt reservation' => 'Choisir la reservation achetee',
        'Choisir koebt kabineklasse' => 'Choisir la classe de cabine achetee',
        'Leveret klasse' => 'Classe fournie',
        'Leveret reservation' => 'Reservation fournie',
        'Leveret kabineklasse' => 'Classe de cabine fournie',
        'Nedgraderet' => 'Declasse',
        'Marker nedgraderet' => 'Marquer comme declasse',
        'Hvis beloebet er for hele billetten, fordeler CakePHP automatisk den relevante andel ud fra de markerede downgradede ben.' => 'Si le montant concerne l ensemble du billet, CakePHP repartit automatiquement la part pertinente selon les segments declines marques.',
        'Endeligt downgrade-beloeb kan stadig beregnes senere i sagen, naar billetpris eller dokumentation er kendt.' => 'Le montant final du declassement pourra encore etre calcule plus tard dans le dossier, lorsque le prix du billet ou la documentation sera connue.',
        'Article 10-satsen afledes automatisk fra distancebÃ¥ndet:' => 'Le taux de l article 10 est derive automatiquement de la tranche de distance :',
    ],
];
$t = static function ($text) use ($uiLanguage, $translations) {
    if (!is_string($text)) {
        return $text;
    }

    return $translations[$uiLanguage][$text] ?? $text;
};
$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}

$v = static fn(string $key, string $fallback = ''): string => (string)($form[$key] ?? $fallback);

$steps = $steps ?? [
    1 => 'Start & Rejsestatus',
    2 => 'Billet / Ticketless + pris',
    3 => 'Rejseoplysninger',
    4 => 'Vaelg afgang + rail-vurdering',
    5 => 'Incident',
    6 => 'Spor efter incident',
    7 => 'Refusion / Omlaegning',
    8 => 'Mad og Hotel',
    9 => 'Nedgradering',
    10 => 'Kompensation',
];
$currentStep = (int)($currentStep ?? 9);
$doneSteps = $doneSteps ?? [];
$backUrl = $backUrl ?? $this->Url->build(['action' => (is_string($flowPrevAction ?? null) && $flowPrevAction !== '' ? $flowPrevAction : 'assistance'), '?' => $flowQuery]);
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ($uiLanguage === 'fr' ? ' etapes' : ' trin')));
$stats = $stats ?? [];

$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail')));
$isAir = $transportMode === 'air';
$isFerry = $transportMode === 'ferry';
$isRail = $transportMode === '' || $transportMode === 'rail';
if ($isAir) {
    $steps = [
        1 => 'Billet, grunddata',
        2 => 'Vaelg fly',
        3 => 'Haendelse',
        4 => 'Valg efter haendelsen',
        5 => 'Refund, ombooking',
        6 => 'Assistance',
        7 => 'Nedgradering',
        8 => 'Kontakt & opret sag',
    ];
    $currentStep = 7;
    $progressPct = (int)(count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0);
    $progressLabel = $currentStep . ' / ' . count($steps) . ($uiLanguage === 'fr' ? ' etapes' : ' trin');
}
$steps = array_map($t, $steps);
$airDistanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($airScope['air_distance_band'] ?? ($meta['air_distance_band'] ?? '')))));
$airAutoRefundPercent = match ($airDistanceBand) {
    'up_to_1500' => '30',
    'intra_eu_over_1500', 'other_1500_to_3500' => '50',
    'other_over_3500' => '75',
    default => '',
};
$airBaseTicketCurrency = strtoupper(trim((string)($form['price_currency'] ?? ($meta['_auto']['price_currency']['value'] ?? 'EUR'))));
if ($airBaseTicketCurrency === '' || $airBaseTicketCurrency === 'AUTO') {
    $airBaseTicketCurrency = 'EUR';
}
$airBaseTicketPrice = trim((string)($form['price'] ?? ''));
if ($airBaseTicketPrice === '') {
    $airBaseTicketPrice = trim((string)($meta['_auto']['price']['value'] ?? ''));
}
$airDowngradeTicketPriceKnown = strtolower((string)($form['air_downgrade_ticket_price_known'] ?? ($airBaseTicketPrice !== '' ? 'yes' : 'no')));
if (!in_array($airDowngradeTicketPriceKnown, ['yes', 'no'], true)) {
    $airDowngradeTicketPriceKnown = $airBaseTicketPrice !== '' ? 'yes' : 'no';
}
$airDowngradeTicketPriceBasis = strtolower(trim((string)($form['air_downgrade_ticket_price_basis'] ?? ($airBaseTicketPrice !== '' ? 'affected_legs' : 'unknown'))));
if (!in_array($airDowngradeTicketPriceBasis, ['affected_legs', 'whole_ticket', 'unknown'], true)) {
    $airDowngradeTicketPriceBasis = $airBaseTicketPrice !== '' ? 'affected_legs' : 'unknown';
}
$airDowngradeTicketPrice = (string)($form['air_downgrade_ticket_price'] ?? ($airBaseTicketPrice !== '' ? preg_replace('/[^0-9.,]/', '', $airBaseTicketPrice) : ''));
$airDowngradeTicketPriceCurrency = strtoupper(trim((string)($form['air_downgrade_ticket_price_currency'] ?? $airBaseTicketCurrency)));
if ($airDowngradeTicketPriceCurrency === '' || $airDowngradeTicketPriceCurrency === 'AUTO') {
    $airDowngradeTicketPriceCurrency = $airBaseTicketCurrency;
}
$airPriceBasisAffectedLabel = $airTopology === 'separate_contracts'
    ? 'Pris for markerede downgradede ben paa den relevante billet'
    : 'Pris for markerede downgradede ben';
$airPriceBasisWholeLabel = $airTopology === 'separate_contracts'
    ? 'Pris for hele den relevante billet'
    : 'Pris for hele billetten';
$airRateLabel = $airAutoRefundPercent !== '' ? ($airAutoRefundPercent . '%') : 'Auto';
$downgradeOccurred = strtolower($v('downgrade_occurred', 'no'));
$basis = $v('downgrade_comp_basis', '');
$segmentShare = $v('downgrade_segment_share', '1');
$missedStation = (string)($form['missed_connection_station'] ?? ($incident['missed_station'] ?? ''));
$article18On = !isset($profile['articles']['art18']) || $profile['articles']['art18'] !== false;
$article182On = !isset($profile['articles']['art18_2']) || $profile['articles']['art18_2'] !== false;

$title = 'Nedgradering';
$subtitle = $isAir
    ? 'Registrer kun dette trin, hvis du faktisk blev placeret i lavere kabineklasse end koebt.'
    : ($isRail
    ? 'Registrer kun dette trin, hvis du faktisk blev placeret i lavere klasse eller mistede en reservation.'
    : 'Registrer kun dette trin, hvis den leverede service var ringere end det, du betalte for.');

$downgradeSummary = $downgradeOccurred === 'yes' ? 'Aktiv' : 'Ikke aktiv';
$downgradeSummaryBadge = $downgradeOccurred === 'yes' ? 'green' : 'gray';

$basisSummary = match ($basis) {
    'seat' => 'Saede',
    'couchette' => 'Ligge',
    'sleeper' => 'Sove',
    default => 'Afventer',
};
$basisSummaryBadge = $basis !== '' ? 'blue' : 'gray';
$downgradeScopeSummaryLabel = $isAir
    ? 'Kabineklasse'
    : ($isRail ? 'Klasse / reservation' : 'Grundlag');
$downgradeScopeSummaryValue = $isAir
    ? ($downgradeOccurred === 'yes' ? 'Klasse tjekkes' : 'Afventer')
    : ($isRail ? ($downgradeOccurred === 'yes' ? 'Tjekkes i tabel' : 'Afventer') : $basisSummary);
$downgradeScopeSummaryBadge = $isAir
    ? ($downgradeOccurred === 'yes' ? 'blue' : 'gray')
    : ($isRail ? ($downgradeOccurred === 'yes' ? 'blue' : 'gray') : $basisSummaryBadge);

$ticketSummary = 'Ikke valgt endnu';
foreach ($downgradeTicketOptions as $option) {
    $candidateFile = (string)($option['file'] ?? '');
    if ($candidateFile !== '' && $candidateFile === $downgradeTicketFile) {
        $ticketSummary = (string)($option['label'] ?? $candidateFile);
        break;
    }
}
if ($ticketSummary === 'Ikke valgt endnu' && count($downgradeTicketOptions) === 1) {
    $ticketSummary = (string)($downgradeTicketOptions[0]['label'] ?? ($downgradeTicketOptions[0]['file'] ?? 'Ikke valgt endnu'));
}

$summaryRows = [
    [$t('Nedgradering'), $t($downgradeSummary), $downgradeSummaryBadge],
    [$t($downgradeScopeSummaryLabel), $t($downgradeScopeSummaryValue), $downgradeScopeSummaryBadge],
    [$t('Billet'), $t($ticketSummary), null],
];

ob_start();
?>
<?= $this->Form->create(null, [
    'url' => ['action' => 'downgrade', '?' => $flowQuery],
    'id' => 'tc6-downgrade-form',
    'class' => 'tc6-form',
    'novalidate' => true,
]) ?>
<?= $this->Form->hidden('downgrade_ticket_file', ['value' => $downgradeTicketFile]) ?>

<div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
<h1 class="tc6-h1"><?= h($title) ?></h1>
  <?php if ($isRail): ?>
<style>
  .tc6-form > .tc6-subtitle,
  .tc6-form .tc6-note,
  .tc6-form .tc6-sc__sub {
    display: none !important;
  }
</style>
<?php else: ?>
<p class="tc6-subtitle"><?= h($subtitle) ?></p>
<?php endif; ?>

<div x-data='{
  downgradeOccurred: <?= json_encode($downgradeOccurred, JSON_UNESCAPED_UNICODE) ?>,
  basis: <?= json_encode($basis, JSON_UNESCAPED_UNICODE) ?>,
  ticketPriceKnown: <?= json_encode($airDowngradeTicketPriceKnown, JSON_UNESCAPED_UNICODE) ?>,
  ticketPriceBasis: <?= json_encode($airDowngradeTicketPriceBasis, JSON_UNESCAPED_UNICODE) ?>
}'>
  <?php if (!$article18On || !$article182On): ?>
    <div class="tc6-note tc6-note--amber">
      Nedgradering kan vaere begraenset af profil/exemptions i denne sag. Visningen her er kun et tyndt tc6-lag; den endelige vurdering ligger stadig i CakePHP.
    </div>
  <?php endif; ?>

  <?php if (count($downgradeTicketOptions) > 1): ?>
    <div class="tc6-card">
      <div class="tc6-section-label">Billetvalg</div>
      <div class="tc6-field">
        <label class="tc6-label">Hvilken billet udfylder du nedgradering for?</label>
        <select class="tc6-select" id="tc6DowngradeTicketSelect">
          <?php foreach ($downgradeTicketOptions as $option):
              $file = (string)($option['file'] ?? '');
              $label = (string)($option['label'] ?? $file);
          ?>
            <option value="<?= h($file) ?>" <?= $file === $downgradeTicketFile ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  <?php endif; ?>

  <div class="tc6-card">
    <div class="tc6-section-label">Blev du nedgraderet?</div>
    <div class="tc6-sc-grid tc6-sc-grid--2">
      <button type="button" class="tc6-sc" :class="{ 'is-selected': downgradeOccurred === 'yes' }" @click="downgradeOccurred = 'yes'">
        <div class="tc6-sc__title">Ja</div>
        <div class="tc6-sc__sub"><?= $isAir ? 'Du blev placeret i en lavere kabineklasse end den, du havde koebt.' : 'Du blev placeret i lavere klasse, eller din reservation blev ikke leveret som koebt.' ?></div>
      </button>
      <button type="button" class="tc6-sc" :class="{ 'is-selected': downgradeOccurred === 'no' }" @click="downgradeOccurred = 'no'">
        <div class="tc6-sc__title">Nej</div>
        <div class="tc6-sc__sub">Det her trin er ikke relevant for den konkrete rejse.</div>
      </button>
    </div>
    <input type="hidden" name="downgrade_occurred" :value="downgradeOccurred || 'no'" />
  </div>

  <div x-show="downgradeOccurred === 'yes'" x-cloak>
    <?php if (!$isAir && !$isRail): ?>
    <div class="tc6-card">
      <div class="tc6-section-label">Grundlag</div>
      <div class="tc6-field-grid--2">
        <div class="tc6-field">
          <label class="tc6-label">Basis (CIV / Bilag II)</label>
          <select class="tc6-select" name="downgrade_comp_basis" x-model="basis">
            <option value="">Vaelg</option>
            <option value="seat">Saede (1 -> 2 klasse)</option>
            <option value="couchette">Ligge (komforttrin ned)</option>
            <option value="sleeper">Sove (komforttrin ned)</option>
          </select>
        </div>
        <div class="tc6-field">
          <label class="tc6-label">Andel af rejsen (0-1)</label>
          <input type="number" min="0" max="1" step="0.01" class="tc6-input" name="downgrade_segment_share" value="<?= h($segmentShare !== '' ? $segmentShare : '1') ?>" />
        </div>
      </div>
    </div>
    <?php else: ?>
    <input type="hidden" name="downgrade_comp_basis" value="<?= h($basis) ?>" />
    <input type="hidden" name="downgrade_segment_share" value="<?= h($segmentShare !== '' ? $segmentShare : '1') ?>" />
    <?php endif; ?>

    <div class="tc6-card">
      <div class="tc6-section-label"><?= $isAir ? 'Per-flight dokumentation' : 'Per-leg dokumentation' ?></div>
      <?= $this->element('downgrade_table', [
          'journeyRowsDowng' => $journeyRowsDowng,
          'form' => $form,
          'meta' => $meta,
          'journey' => $journey,
          'missedStation' => $missedStation,
          'affectedLegsAuto' => $affectedLegsAuto ?? [],
          'isAir' => $isAir,
          'isFerry' => $isFerry,
      ]) ?>
    </div>

    <?php if ($isAir): ?>
    <div class="tc6-card">
      <div class="tc6-section-label">Relevant billetpris for nedgradering</div>
      <div class="tc6-field">
        <div class="tc6-label">Kender du billetprisen for den relevante flyvning eller billet?</div>
        <div class="tc6-choice-cards tc6-choice-cards--2">
          <label class="tc6-choice-card">
            <input type="radio" name="air_downgrade_ticket_price_known" value="yes" x-model="ticketPriceKnown" <?= $airDowngradeTicketPriceKnown === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="air_downgrade_ticket_price_known" value="no" x-model="ticketPriceKnown" <?= $airDowngradeTicketPriceKnown !== 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
          </label>
        </div>
      </div>

      <div class="tc6-field-grid--3" x-show="ticketPriceKnown === 'yes'" x-cloak style="margin-top:14px">
        <div class="tc6-field">
          <label class="tc6-label">Prisgrundlag</label>
          <select class="tc6-select" name="air_downgrade_ticket_price_basis" x-model="ticketPriceBasis">
            <option value="affected_legs" <?= $airDowngradeTicketPriceBasis === 'affected_legs' ? 'selected' : '' ?>><?= h($airPriceBasisAffectedLabel) ?></option>
            <option value="whole_ticket" <?= $airDowngradeTicketPriceBasis === 'whole_ticket' ? 'selected' : '' ?>><?= h($airPriceBasisWholeLabel) ?></option>
            <option value="unknown" <?= $airDowngradeTicketPriceBasis === 'unknown' ? 'selected' : '' ?>>Ved ikke</option>
          </select>
        </div>
        <div class="tc6-field">
          <label class="tc6-label">Billetpris</label>
          <input type="number" min="0" step="0.01" class="tc6-input" name="air_downgrade_ticket_price" value="<?= h($airDowngradeTicketPrice) ?>" />
        </div>
        <div class="tc6-field">
          <label class="tc6-label">Valuta</label>
          <select class="tc6-select" name="air_downgrade_ticket_price_currency">
            <?php foreach (['EUR', 'DKK', 'SEK', 'NOK', 'GBP', 'CHF', 'BGN', 'CZK', 'HUF', 'PLN', 'RON', 'USD', 'CAD'] as $cur): ?>
              <option value="<?= h($cur) ?>" <?= $airDowngradeTicketPriceCurrency === $cur ? 'selected' : '' ?>><?= h($cur) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="tc6-note tc6-note--blue" x-show="ticketPriceKnown === 'yes' && ticketPriceBasis === 'whole_ticket'" x-cloak style="margin-top:14px; margin-bottom:0;">
        Hvis beloebet er for hele billetten, fordeler CakePHP automatisk den relevante andel ud fra de markerede downgradede ben.
      </div>

      <div class="tc6-note tc6-note--blue" x-show="ticketPriceKnown !== 'yes'" x-cloak style="margin-top:14px; margin-bottom:0;">
        Endeligt downgrade-beloeb kan stadig beregnes senere i sagen, naar billetpris eller dokumentation er kendt.
      </div>

      <div class="tc6-note tc6-note--blue" style="margin-top:14px; margin-bottom:0;">
        Article 10-satsen afledes automatisk fra distancebåndet: <strong><?= h($airRateLabel) ?></strong>.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?= $this->element('tc6/action_bar', [
    'backUrl' => $backUrl,
    'backLabel' => $t('Tilbage'),
    'nextLabel' => $t('Naeste trin'),
    'nextVariant' => 'navy',
    'submitName' => '_save',
]) ?>

<?= $this->Form->end() ?>

<?php
$content = ob_get_clean();
if ($uiLanguage !== 'da' && isset($translations[$uiLanguage])) {
    $content = strtr($content, $translations[$uiLanguage]);
    if ($uiLanguage === 'fr') {
        $content = str_replace(
            [
                'Choisir koebt niveau',
                'Choisir koebt reservation',
                'Choisir koebt kabineklasse',
                'koebt niveau',
                'koebt reservation',
                'koebt kabineklasse',
            ],
            [
                'Choisir le niveau achete',
                'Choisir la reservation achetee',
                'Choisir la classe de cabine achetee',
                'niveau achete',
                'reservation achetee',
                'classe de cabine achetee',
            ],
            $content
        );
    }
}

ob_start();
echo $this->element('tc6/live_estimate_router', [
    'form' => $form,
    'flags' => $flags,
    'meta' => $meta,
    'journey' => $journey ?? [],
]);
$liveEstimateHtml = trim((string)ob_get_clean());

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => $compensation ?? null,
    'compRate' => $compRate ?? null,
    'compBase' => $compBase ?? null,
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => $stats,
    'summaryRows' => $summaryRows,
    'liveEstimateHtml' => $liveEstimateHtml,
    'nextHint' => '',
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel') + [
    'context' => $t((strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed'))) === 'ongoing') ? 'Igangvaerende rejse' : 'Afsluttet rejse'),
]);
?>
<?php if ($airDowngradeRejectedAlert !== ''): ?>
<script>
  window.alert(<?= json_encode($airDowngradeRejectedAlert, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
</script>
<?php endif; ?>
