<?php
/**
 * TC6 rail applicant & payout step.
 *
 * Visual layer only. Uses existing applicant action + fields.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$translations = [
    'fr' => [
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Start & Rejsestatus' => 'Depart et statut du voyage',
        'Start &amp; Rejsestatus' => 'Depart et statut du voyage',
        'Billet / Ticketless + Grunddata' => 'Billet / sans billet + donnees de base',
        'Haendelseskede + gating' => 'Chaine d incident + filtrage',
        'Refusion / Omlaegning (Art. 18)' => 'Remboursement / reacheminement (art. 18)',
        'Beregning & Resultat (Art. 19)' => 'Calcul et resultat (art. 19)',
        'Ansoeger & Udbetaling' => 'Demandeur et paiement',
        'Ansoeger &amp; Udbetaling' => 'Demandeur et paiement',
        'Samtykke & Ekstra info' => 'Consentement et informations complementaires',
        'Samtykke &amp; Ekstra info' => 'Consentement et informations complementaires',
        'Igangvaerende rejse' => 'Voyage en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Voyage termine',
        'Kontakt' => 'Contact',
        'Telefon' => 'Telephone',
        'Udbetaling' => 'Paiement',
        'Klar' => 'Pret',
        'Registreret' => 'Enregistre',
        'Afventer' => 'En attente',
        'Flow' => 'Parcours',
        'Ansoeger & udbetaling' => 'Demandeur et paiement',
        'Rute' => 'Itineraire',
        'Operatoer' => 'Operateur',
        'Bookingreference' => 'Reference de reservation',
        'Kontaktoplysninger' => 'Coordonnees',
        'Fornavn' => 'Prenom',
        'Efternavn' => 'Nom',
        'Telefonnummer' => 'Numero de telephone',
        'Adresse' => 'Adresse',
        'Vej' => 'Rue',
        'Nr.' => 'No',
        'Postnr.' => 'Code postal',
        'By' => 'Ville',
        'Land' => 'Pays',
        'Udbetalingsmetode' => 'Mode de paiement',
        'Bankoverfoersel' => 'Virement bancaire',
        'Kontohaver' => 'Titulaire du compte',
        'Andet' => 'Autre',
        'Voucher' => 'Bon',
    ],
];
$t = static function ($text) use ($uiLanguage, $translations) {
    if (!is_string($text)) {
        return $text;
    }

    if (isset($translations[$uiLanguage][$text])) {
        return $translations[$uiLanguage][$text];
    }

    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $translations[$uiLanguage][$decoded] ?? $text;
};

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$travelStateLabel = match ($travelState) {
    'ongoing' => $t('Igangvaerende rejse'),
    'before_start' => $t('Foer afgang'),
    default => $t('Afsluttet rejse'),
};

$steps = $steps ?? [
    1 => 'Start & Rejsestatus',
    2 => 'Billet / Ticketless + Grunddata',
    3 => 'Haendelseskede + gating',
    4 => 'Refusion / Omlaegning (Art. 18)',
    5 => 'Beregning & Resultat (Art. 19)',
    6 => 'Ansoeger & Udbetaling',
    7 => 'Samtykke & Ekstra info',
];
$currentStep = (int)($currentStep ?? 6);
$doneSteps = $doneSteps ?? [];
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ' trin'));
if ($uiLanguage === 'fr') {
    $progressLabel = $currentStep . ' / ' . count($steps) . ' etapes';
}

$routeLabel = trim((string)($form['dep_station'] ?? ($journey['origin']['value'] ?? '')) . ' -> ' . (string)($form['arr_station'] ?? ($journey['destination']['value'] ?? '')));
$operatorLabel = trim((string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')));
$bookingReference = trim((string)($form['booking_reference'] ?? ($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))));
$email = trim((string)($form['contact_email'] ?? ''));
$phone = trim((string)($form['contact_phone'] ?? ''));
$country = trim((string)($form['address_country'] ?? ''));
$payoutPreference = trim((string)($form['payoutPreference'] ?? ''));
$payoutLabel = match ($payoutPreference) {
    'bank' => $t('Bankoverfoersel'),
    'voucher' => $t('Voucher'),
    'other' => $t('Andet'),
    default => $t('Afventer'),
};

$steps = array_map($t, $steps);
$summaryRows = [
    [$t('Kontakt'), $email !== '' ? $t('Klar') : $t('Afventer'), $email !== '' ? 'green' : 'gray'],
    [$t('Telefon'), $phone !== '' ? $t('Registreret') : $t('Afventer'), $phone !== '' ? 'blue' : 'gray'],
    [$t('Udbetaling'), $payoutLabel, $payoutPreference !== '' ? 'blue' : 'gray'],
];

$stats = $stats ?? [
    [$t('Flow'), $travelStateLabel, null],
    ['Transport', 'RAIL', null],
];
$context = $travelStateLabel;
$brandName = 'TrainClaim';
$brandMark = 'TC';

$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$backUrl = $this->Url->build(['action' => 'compensation', '?' => $flowQuery]);

ob_start();
?>
<?= $this->Form->create(null, [
    'url' => ['action' => 'applicant', '?' => $flowQuery],
    'id' => 'tc6-applicant-form',
    'class' => 'tc6-form',
    'novalidate' => true,
]) ?>

<div class="tc6-applicant">
  <div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
  <h1 class="tc6-h1"><?= h($t('Ansoeger & udbetaling')) ?></h1>
  <?php /* helper copy removed */ ?>

  <?= $this->element('flow_locked_notice') ?>

  <div class="tc6-grid-3">
    <?php if ($routeLabel !== '->' && trim($routeLabel) !== ''): ?>
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Rute')) ?></div>
        <div class="tc6-meta-pill__value"><?= h($routeLabel) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($operatorLabel !== ''): ?>
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Operatoer')) ?></div>
        <div class="tc6-meta-pill__value"><?= h($operatorLabel) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($bookingReference !== ''): ?>
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Bookingreference')) ?></div>
        <div class="tc6-meta-pill__value"><?= h($bookingReference) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="tc6-card">
    <div class="tc6-section-head">
      <div class="tc6-section-label"><?= h($t('Kontaktoplysninger')) ?></div>
      <?php /* helper copy removed */ ?>
    </div>
    <div class="tc6-grid-2">
      <div class="tc6-field">
        <?= $this->Form->control('firstName', ['label' => $t('Fornavn'), 'value' => (string)($form['firstName'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('lastName', ['label' => $t('Efternavn'), 'value' => (string)($form['lastName'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('contact_email', ['label' => 'E-mail', 'type' => 'email', 'value' => $email, 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('contact_phone', ['label' => $t('Telefonnummer'), 'value' => $phone, 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
    </div>
  </div>

  <div class="tc6-card">
    <div class="tc6-section-head">
      <div class="tc6-section-label"><?= h($t('Adresse')) ?></div>
      <?php /* helper copy removed */ ?>
    </div>
    <div class="tc6-grid-2">
      <div class="tc6-field">
        <?= $this->Form->control('address_street', ['label' => $t('Vej'), 'value' => (string)($form['address_street'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('address_no', ['label' => $t('Nr.'), 'value' => (string)($form['address_no'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('address_postalCode', ['label' => $t('Postnr.'), 'value' => (string)($form['address_postalCode'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('address_city', ['label' => $t('By'), 'value' => (string)($form['address_city'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('address_country', ['label' => $t('Land'), 'value' => $country, 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
    </div>
  </div>

  <div class="tc6-card">
    <div class="tc6-section-head">
      <div class="tc6-section-label"><?= h($t('Udbetaling')) ?></div>
      <?php /* helper copy removed */ ?>
    </div>
    <div class="tc6-grid-2">
      <div class="tc6-field" style="grid-column: 1 / -1;">
        <?= $this->Form->control('payoutPreference', [
            'label' => $t('Udbetalingsmetode'),
            'options' => ['bank' => $t('Bankoverfoersel'), 'voucher' => $t('Voucher'), 'other' => $t('Andet')],
            'empty' => true,
            'value' => $payoutPreference,
            'templates' => ['inputContainer' => '{{content}}'],
        ]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('accountHolderName', ['label' => $t('Kontohaver'), 'value' => (string)($form['accountHolderName'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('iban', ['label' => 'IBAN', 'value' => (string)($form['iban'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
      <div class="tc6-field">
        <?= $this->Form->control('bic', ['label' => 'BIC', 'value' => (string)($form['bic'] ?? ''), 'templates' => ['inputContainer' => '{{content}}']]) ?>
      </div>
    </div>
  </div>

<?= $this->element('tc6/action_bar', [
    'backUrl' => $backUrl,
    'backLabel' => $t('Tilbage'),
    'nextLabel' => $t('Naeste trin'),
    'nextVariant' => 'navy',
    'submitName' => '_save',
]) ?>
</div>

<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'applicant', 'formSelector' => '#tc6-applicant-form']) ?>
<?php
$content = ob_get_clean();
if ($uiLanguage !== 'da' && isset($translations[$uiLanguage])) {
    $content = strtr($content, $translations[$uiLanguage]);
}

ob_start();
echo $this->element('tc6/live_estimate_router', [
    'form' => $form,
    'flags' => $flags,
    'meta' => $meta,
    'journey' => $journey,
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
    'nextHint' => '',
    'liveEstimateHtml' => $liveEstimateHtml,
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel', 'context', 'brandName', 'brandMark'));
