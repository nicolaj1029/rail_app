<?php
/** @var \App\View\AppView $this */
$compute = $compute ?? [];
$form = $form ?? [];
$incident = $incident ?? [];
?>
<h1>TRIN 3 · Rejsedetaljer (Upload billet)</h1>
<?php
  // UI banners derived from exemption profile (global notices)
  $uiBanners = (array)($profile['ui_banners'] ?? []);
  if (!empty($uiBanners)) {
      echo '<div class="small" style="margin-top:6px;">';
      foreach ($uiBanners as $ban) {
          echo '<div style="background:#fff3cd; border:1px solid #eed27c; padding:6px; border-radius:6px; margin-top:6px;">' . h($ban) . '</div>';
      }
      echo '</div>';
  }
  $articles = (array)($profile['articles'] ?? []);
  $articlesSub = (array)($profile['articles_sub'] ?? []);
  // Per clarification: pricing, class, and pre-purchase disclosure all fall under Art. 9 stk. 1
  $showArt9_1 = !isset($articlesSub['art9_1']) || $articlesSub['art9_1'] !== false;
?>
<?= $this->Form->create(null, ['type' => 'file', 'id' => 'entitlementsForm']) ?>
  <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>Billetter</strong>
  <div id="uploadDropzone" tabindex="0" style="margin-top:8px; border:2px dashed #9bb; background:#f7fbff; border-radius:8px; padding:12px; min-height:100px; text-align:center; cursor:pointer;">
      <div style="font-size:14px; color:#345;">Slip filer her eller klik for at tilføje</div>
      <div class="small muted" style="margin-top:6px;">Understøtter PDF, JPG, PNG, PKPASS, TXT</div>
      <div style="margin-top:10px; display:flex; gap:8px; justify-content:center;">
        <button type="button" id="addFilesBtn" class="button">Tilføj filer</button>
        <button type="button" id="clearFilesBtn" class="button" style="background:#eee; color:#333;">Fjern alle</button>
      </div>
    </div>
    <!-- Hidden real inputs wired by JS -->
    <input type="file" id="ticketSingle" name="ticket_upload" accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" style="display:none;" />
    <input type="file" id="ticketMulti" name="multi_ticket_upload[]" multiple accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" style="display:none;" />
    <ul id="selectedFilesList" class="small" style="list-style:none; padding-left:0; margin:12px 0 0 0;"></ul>
  </div>
  <?php
    // Lightweight status so users see that parsing happened even when no choices are shown
    $segCountTop = isset($meta['_segments_auto']) && is_array($meta['_segments_auto']) ? count((array)$meta['_segments_auto']) : 0;
    $llmFlagRawTop = function_exists('env') ? env('USE_LLM_STRUCTURING') : getenv('USE_LLM_STRUCTURING');
    $llmOnTop = in_array(strtolower((string)$llmFlagRawTop), ['1','true','yes','on'], true);
  ?>
  <div class="small muted" style="margin-top:6px;">
    Auto: <?= (int)$segCountTop ?> segmenter fundet<?= $segCountTop===0 ? (' — LLM-strukturering: ' . ($llmOnTop ? 'til' : 'fra')) : '' ?>.
  </div>

  <?php
    // PMR block gating
    $pmrAuto = (array)($meta['_pmr_detection'] ?? []);
    $pmrDetectedFlag = !empty($meta['_pmr_detected']) || !empty($pmrAuto);
    // Use Art.9 hooks if present for normalized echo, falling back to meta
    $art9Hooks = is_array($art9??null) ? (array)($art9['hooks'] ?? []) : [];
    $pmrUserVal = strtolower((string)($art9Hooks['pmr_user'] ?? ($meta['pmr_user'] ?? 'unknown')));
    // Accept both 'Ja'/'Nej' and 'yes'/'no' variants
    if ($pmrUserVal==='ja') $pmrUserVal='yes'; if ($pmrUserVal==='nej') $pmrUserVal='no';
    $showPmr = $pmrDetectedFlag || $pmrUserVal==='yes';
    $pmrBookedVal = strtolower((string)($art9Hooks['pmr_booked'] ?? ($meta['pmr_booked'] ?? 'unknown')));
    if ($pmrBookedVal==='ja') $pmrBookedVal='yes'; if ($pmrBookedVal==='nej') $pmrBookedVal='no';
    $pmrDeliveredVal = strtolower((string)($art9Hooks['pmr_delivered_status'] ?? ($meta['pmr_delivered_status'] ?? 'unknown')));
    $pmrPromisedMissingVal = strtolower((string)($art9Hooks['pmr_promised_missing'] ?? ($meta['pmr_promised_missing'] ?? 'unknown')));
    $pmrFacilityDetails = (string)($meta['pmr_facility_details'] ?? '');
  ?>
  <?php if ($showPmr): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;" id="pmrFlowCard">
    <strong>♿ PMR/handicap (Art. 18 og 20)</strong>
    <?php if ($pmrDetectedFlag): ?>
      <div class="small" style="margin-top:6px;">
        <span class="badge" style="background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">Auto</span>
        Vi har fundet PMR/assistance i billetten — du kan have ekstra rettigheder.
      </div>
    <?php endif; ?>
    <div class="small" style="margin-top:8px;">
      <div><strong>Spm 1.</strong> Har du et handicap eller nedsat mobilitet, som krævede assistance?</div>
      <label class="mr8"><input type="radio" name="pmr_user" value="yes" <?= $pmrUserVal==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="pmr_user" value="no" <?= $pmrUserVal==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div id="pmrQBooked" class="small" style="margin-top:8px; display:<?= ($pmrUserVal==='yes')?'block':'none' ?>;">
      <div><strong>Spm 2.</strong> Bestilte du assistance før rejsen?</div>
      <label class="mr8"><input type="radio" name="pmr_booked" value="yes" <?= $pmrBookedVal==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="pmr_booked" value="no" <?= $pmrBookedVal==='no'?'checked':'' ?> /> Nej</label>
      <label class="mr8"><input type="radio" name="pmr_booked" value="refused" <?= $pmrBookedVal==='refused'?'checked':'' ?> /> Forsøgte men fik afslag</label>
    </div>
    <div id="pmrQDelivered" class="small" style="margin-top:8px; display:<?= ($pmrUserVal==='yes' && $pmrBookedVal!=='no')?'block':'none' ?>;">
      <div><strong>Spm 3.</strong> Blev den bestilte assistance leveret?</div>
      <select name="pmr_delivered_status">
        <option value="">— vælg —</option>
        <option value="yes_full" <?= $pmrDeliveredVal==='yes_full'?'selected':'' ?>>Ja, fuldt ud</option>
        <option value="partial" <?= $pmrDeliveredVal==='partial'?'selected':'' ?>>Delvist</option>
        <option value="no" <?= $pmrDeliveredVal==='no'?'selected':'' ?>>Nej</option>
      </select>
    </div>
    <div id="pmrQPromised" class="small" style="margin-top:8px; display:<?= ($pmrUserVal==='yes')?'block':'none' ?>;">
      <div><strong>Spm 4.</strong> Manglede der PMR-faciliteter, som var lovet før købet?</div>
      <label class="mr8"><input type="radio" name="pmr_promised_missing" value="yes" <?= $pmrPromisedMissingVal==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="pmr_promised_missing" value="no" <?= $pmrPromisedMissingVal==='no'?'checked':'' ?> /> Nej</label>
      <label class="mr8"><input type="radio" name="pmr_promised_missing" value="unknown" <?= ($pmrPromisedMissingVal===''||$pmrPromisedMissingVal==='unknown')?'checked':'' ?> /> Ved ikke</label>
    </div>
    <div id="pmrQDetails" class="small" style="margin-top:8px; display:<?= ($pmrPromisedMissingVal==='yes')?'block':'none' ?>;">
      <div><strong>Spm 5.</strong> Hvilke faciliteter manglede? (rampe, skiltning, lift …)</div>
      <textarea name="pmr_facility_details" rows="2" style="width:100%;" placeholder="Beskriv kort"><?= h($pmrFacilityDetails) ?></textarea>
    </div>
  </div>
  <?php endif; ?>

  <?php
    // Bike flow visibility gating: show the block and preselect if OCR detected a bike on ticket
    $bikeAuto = (array)($meta['_bike_detection'] ?? []);
    $bikeBookedAuto = (string)($meta['_auto']['bike_booked']['value'] ?? '') === 'Ja' || (string)($meta['bike_booked'] ?? '') === 'Ja';
    $bikeWas = strtolower((string)($meta['bike_was_present'] ?? ($bikeBookedAuto ? 'yes' : '')));
    $bikeCause = strtolower((string)($meta['bike_caused_issue'] ?? ''));
    $bikeResMade = strtolower((string)($meta['bike_reservation_made'] ?? ''));
    $bikeResReq = strtolower((string)($meta['bike_reservation_required'] ?? ''));
    $bikeDenied = strtolower((string)($meta['bike_denied_boarding'] ?? ''));
    $bikeReasonProv = strtolower((string)($meta['bike_refusal_reason_provided'] ?? ''));
    $bikeReasonType = strtolower((string)($meta['bike_refusal_reason_type'] ?? ''));
  ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;" id="bikeFlowCard">
    <strong>🚲 Cykel på rejsen (Artikel 6)</strong>
    <?php if (!empty($bikeAuto)): ?>
      <div class="small" style="margin-top:6px;">
        <span class="badge" style="background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">Auto</span>
        Vi har fundet cykelhenvisning på billetten — du har muligvis rettigheder efter Art. 6.
        <?php if (!empty($bikeAuto['count'])): ?><span class="ml8">(antal: <?= (int)$bikeAuto['count'] ?>)</span><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="small" style="margin-top:8px;">
      <div><strong>Spm 1.</strong> Havde du en cykel med på rejsen?</div>
      <?php $w = $bikeWas !== '' ? $bikeWas : ($bikeBookedAuto ? 'yes' : ''); ?>
      <label class="mr8"><input type="radio" name="bike_was_present" value="yes" <?= $w==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="bike_was_present" value="no" <?= $w==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div class="small" id="bikeQ2" style="margin-top:8px; display:<?= ($w==='yes')?'block':'none' ?>;">
      <div><strong>Spm 2.</strong> Er det cyklen eller håndteringen af cyklen, der har forsinket dig?</div>
      <label class="mr8"><input type="radio" name="bike_caused_issue" value="yes" <?= $bikeCause==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="bike_caused_issue" value="no" <?= $bikeCause==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div id="bikeArticle6" style="margin-top:8px; display:<?= ($w==='yes' && $bikeCause==='yes')?'block':'none' ?>;">
      <div class="small"><strong>Spm 3.</strong> Havde du reserveret plads til en cykel?</div>
      <div class="small" style="margin-top:4px;">
        <label class="mr8"><input type="radio" name="bike_reservation_made" value="yes" <?= $bikeResMade==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="bike_reservation_made" value="no" <?= $bikeResMade==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div id="bikeQ3B" class="small" style="margin-top:8px; display:<?= ($bikeResMade==='no')?'block':'none' ?>;">
        <div><strong>Spm 3B.</strong> Var det et tog, hvor der ikke krævedes cykelreservation?</div>
        <label class="mr8"><input type="radio" name="bike_reservation_required" value="yes" <?= $bikeResReq==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="bike_reservation_required" value="no" <?= $bikeResReq==='no'?'checked':'' ?> /> Nej</label>
        <label class="mr8"><input type="radio" name="bike_reservation_required" value="unknown" <?= ($bikeResReq===''||$bikeResReq==='unknown')?'checked':'' ?> /> Ved ikke</label>
      </div>
      <div id="bikeQ4" class="small" style="margin-top:8px;">
        <div><strong>Spm 4.</strong> Blev du nægtet at tage cyklen med?</div>
        <label class="mr8"><input type="radio" name="bike_denied_boarding" value="yes" <?= $bikeDenied==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="bike_denied_boarding" value="no" <?= $bikeDenied==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div id="bikeQ5" class="small" style="margin-top:8px; display:<?= ($bikeDenied==='yes')?'block':'none' ?>;">
        <div><strong>Spm 5.</strong> Blev du informeret om, hvorfor du ikke måtte tage cyklen med?</div>
        <label class="mr8"><input type="radio" name="bike_refusal_reason_provided" value="yes" <?= $bikeReasonProv==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="bike_refusal_reason_provided" value="no" <?= $bikeReasonProv==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div id="bikeQ6" class="small" style="margin-top:8px; display:<?= ($bikeDenied==='yes' && $bikeReasonProv==='yes')?'block':'none' ?>;">
        <div><strong>Spm 6.</strong> Hvad var begrundelsen for afvisningen?</div>
        <?php $opt = $bikeReasonType; ?>
        <select name="bike_refusal_reason_type">
          <option value="">— vælg —</option>
          <option value="capacity" <?= $opt==='capacity'?'selected':'' ?>>Pladsmangel / Spidsbelastning</option>
          <option value="equipment" <?= $opt==='equipment'?'selected':'' ?>>Teknisk udstyr tillader det ikke</option>
          <option value="weight_dim" <?= $opt==='weight_dim'?'selected':'' ?>>Vægt eller dimensioner</option>
          <option value="other" <?= $opt==='other'?'selected':'' ?>>Andet</option>
          <option value="unknown" <?= $opt==='unknown'?'selected':'' ?>>Ved ikke</option>
        </select>
      </div>
    </div>
  </div>

  <?php
    // 3) Billetpriser og fleksibilitet (Art. 9) — show simple Qs with auto-prefill
    $fftVal = (string)($meta['fare_flex_type'] ?? ($meta['_auto']['fare_flex_type']['value'] ?? ''));
    $tsVal = (string)($meta['train_specificity'] ?? ($meta['_auto']['train_specificity']['value'] ?? 'unknown'));
    $hasAutoPricing = !empty($meta['_auto']['fare_flex_type']['value'] ?? null) || !empty($meta['_auto']['train_specificity']['value'] ?? null);
  ?>
  <?php if ($showArt9_1): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;" id="pricingBlock" data-art="9(1)">
    <strong>💶 3) Billetpriser og fleksibilitet (Art. 9 stk. 1)</strong>
    <?php if ($hasAutoPricing): ?>
      <div class="small" style="margin-top:6px;">
        <span class="badge" style="background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">Auto</span>
        Vi har et bud på købstype og togbinding — ret selv hvis det ikke passer.
      </div>
    <?php endif; ?>
    <div class="mt8 small">1. Købstype (fleksibilitet)</div>
    <?php $curFft = strtolower($fftVal); ?>
    <select name="fare_flex_type">
      <option value="" <?= $curFft===''?'selected':'' ?>>— vælg —</option>
      <option value="nonflex" <?= $curFft==='nonflex'?'selected':'' ?>>Standard/Non-flex</option>
      <option value="semiflex" <?= $curFft==='semiflex'?'selected':'' ?>>Semi-flex</option>
      <option value="flex" <?= $curFft==='flex'?'selected':'' ?>>Flex</option>
      <option value="pass" <?= $curFft==='pass'?'selected':'' ?>>Abonnement/Periodekort</option>
      <option value="other" <?= $curFft==='other'?'selected':'' ?>>Andet</option>
    </select>

    <?php
      // Season/period pass details (Art. 19(2)) — shown when "Abonnement/Periodekort" is selected
      $season = (array)($meta['season_pass'] ?? []);
      $seasonHas = ($curFft === 'pass') || !empty($season['has']);
      $seasonType = (string)($season['type'] ?? '');
      $seasonOp = (string)($season['operator'] ?? ($meta['_auto']['operator']['value'] ?? ($form['operator'] ?? '')));
      $seasonFrom = (string)($season['valid_from'] ?? '');
      $seasonTo = (string)($season['valid_to'] ?? '');
    ?>
    <div id="seasonPassBlock" class="mt8" style="display:<?= $seasonHas ? 'block' : 'none' ?>;">
      <div class="small" style="margin-bottom:6px;">
        🔹 Abonnement/Periodekort (Art. 19, stk. 2) — gentagne forsinkelser i gyldighedsperioden kan udløse kompensation efter operatørens ordning.
      </div>
      <input type="hidden" name="season_pass_has" value="<?= $seasonHas ? '1' : '' ?>" />
      <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
        <label>Type
          <input type="text" name="season_pass_type" value="<?= h($seasonType) ?>" placeholder="Pendler / Periode / Årskort" />
        </label>
        <label>Operatør
          <input type="text" name="season_pass_operator" value="<?= h($seasonOp) ?>" placeholder="DSB / DB / SNCF …" />
        </label>
        <label>Gyldig fra (YYYY-MM-DD)
          <input type="text" name="season_pass_valid_from" value="<?= h($seasonFrom) ?>" placeholder="YYYY-MM-DD" />
        </label>
        <label>Gyldig til (YYYY-MM-DD)
          <input type="text" name="season_pass_valid_to" value="<?= h($seasonTo) ?>" placeholder="YYYY-MM-DD" />
        </label>
      </div>
      <div class="small muted" style="margin-top:6px;">Tip: Små forsinkelser (&lt; 60 min) kan kumuleres i perioden efter operatørens ordning.</div>
    </div>

    <div class="mt8 small">2. Gælder billetten kun for specifikt tog?</div>
    <?php $curTs = strtolower($tsVal ?: 'unknown'); ?>
    <label class="small"><input type="radio" name="train_specificity" value="specific" <?= $curTs==='specific'?'checked':'' ?> /> Kun specifikt tog</label>
    <label class="small ml8"><input type="radio" name="train_specificity" value="any_day" <?= $curTs==='any_day'?'checked':'' ?> /> Vilkårlig afgang samme dag</label>
    <label class="small ml8"><input type="radio" name="train_specificity" value="unknown" <?= ($curTs===''||$curTs==='unknown')?'checked':'' ?> /> Ved ikke</label>
  </div>
  <?php else: ?>
    <div class="small" style="margin-top:12px; background:#f6f7f9; border:1px solid #e2e6ea; padding:6px; border-radius:6px;">Billetpriser og fleksibilitet (Art. 9 stk. 1) er undtaget for denne rejse og vises ikke.</div>
  <?php endif; ?>

  <?php
    // 6) Klasse og reserverede faciliteter — altid synlig i TRIN 3
    $art9Hooks = is_array($art9??null) ? (array)($art9['hooks'] ?? []) : [];
    $fcp = (string)($meta['fare_class_purchased'] ?? ($art9Hooks['fare_class_purchased'] ?? ''));
    $bst = (string)($meta['berth_seat_type'] ?? ($art9Hooks['berth_seat_type'] ?? ''));
    $cds = (string)($meta['class_delivered_status'] ?? ($art9Hooks['class_delivered_status'] ?? ''));
    $rad = (string)($meta['reserved_amenity_delivered'] ?? ($art9Hooks['reserved_amenity_delivered'] ?? ''));
    $classAuto = (array)($meta['_class_detection'] ?? []);
  ?>
  <?php if ($showArt9_1): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;" id="classBlock" data-art="9(1)">
    <strong>💺 6) Klasse og reserverede faciliteter (Art. 9 stk. 1)</strong>
    <?php if (!empty($classAuto) || !empty($meta['_auto']['fare_class_purchased']['value'] ?? null) || !empty($meta['_auto']['berth_seat_type']['value'] ?? null)): ?>
      <div class="small" style="margin-top:6px;">
        <span class="badge" style="background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">Auto</span>
        Vi har fundet klasse/reservation i billetten — ret selv hvis det ikke passer.
      </div>
    <?php endif; ?>
    <div class="mt8 small">1. Hvilken klasse var købt?</div>
    <select name="fare_class_purchased">
      <option value="" <?= $fcp===''?'selected':'' ?>>— vælg —</option>
      <option value="1" <?= $fcp==='1'?'selected':'' ?>>1. klasse</option>
      <option value="2" <?= $fcp==='2'?'selected':'' ?>>2. klasse</option>
      <option value="other" <?= $fcp==='other'?'selected':'' ?>>Andet</option>
      <option value="unknown" <?= $fcp==='unknown'?'selected':'' ?>>Ved ikke</option>
    </select>

    <?php $showQ2 = ($fcp !== '' && $fcp !== 'unknown'); ?>
    <div id="classQ2" class="mt8" style="display:<?= $showQ2 ? 'block' : 'none' ?>;">
      <div class="small">2. Fik du den klasse, du betalte for?</div>
      <label class="small"><input type="radio" name="class_delivered_status" value="ok" <?= $cds==='ok'?'checked':'' ?> /> Ja</label>
      <label class="small ml8"><input type="radio" name="class_delivered_status" value="downgrade" <?= $cds==='downgrade'?'checked':'' ?> /> Nej, nedklassificeret</label>
      <label class="small ml8"><input type="radio" name="class_delivered_status" value="upgrade" <?= $cds==='upgrade'?'checked':'' ?> /> Nej, opgraderet</label>
    </div>

    <div class="mt8 small">3. Var der reserveret plads/kupe/ligge/sove?</div>
    <select name="berth_seat_type">
      <option value="" <?= $bst===''?'selected':'' ?>>— vælg —</option>
      <option value="seat" <?= $bst==='seat'?'selected':'' ?>>Fast sæde</option>
      <option value="free" <?= $bst==='free'?'selected':'' ?>>Fri plads</option>
      <option value="couchette" <?= $bst==='couchette'?'selected':'' ?>>Liggevogn</option>
      <option value="sleeper" <?= $bst==='sleeper'?'selected':'' ?>>Sovevogn</option>
      <option value="none" <?= $bst==='none'?'selected':'' ?>>Ingen</option>
    </select>

    <?php $needsDelivery = in_array($bst, ['seat','couchette','sleeper'], true); ?>
    <div id="classQ4" class="mt8" style="display:<?= $needsDelivery ? 'block' : 'none' ?>;">
      <div class="small">4. Blev reserveret plads/ligge/sove leveret?</div>
      <label class="small"><input type="radio" name="reserved_amenity_delivered" value="yes" <?= $rad==='yes'?'checked':'' ?> /> Ja</label>
      <label class="small ml8"><input type="radio" name="reserved_amenity_delivered" value="no" <?= $rad==='no'?'checked':'' ?> /> Nej</label>
      <label class="small ml8"><input type="radio" name="reserved_amenity_delivered" value="partial" <?= $rad==='partial'?'checked':'' ?> /> Delvist</label>
    </div>
  </div>
  <?php else: ?>
    <div class="small" style="margin-top:12px; background:#f6f7f9; border:1px solid #e2e6ea; padding:6px; border-radius:6px;">Klasse og reserverede faciliteter (Art. 9 stk. 1) er undtaget for denne rejse og vises ikke.</div>
  <?php endif; ?>

  <?php
    // 7) Afbrydelser/forsinkelser før køb (Art. 9(1))
    // Q1 vises altid; Q2+Q3 vises når Q1=Ja. Q3 kun hvis Art. 10 gælder (realtime information).
    $art10Applies = $profile['articles']['art10'] ?? true;
    $pid = (string)($form['preinformed_disruption'] ?? ($art9['hooks']['preinformed_disruption'] ?? ''));
    $pic = (string)($form['preinfo_channel'] ?? ($art9['hooks']['preinfo_channel'] ?? ''));
    $ris = (string)($form['realtime_info_seen'] ?? ($art9['hooks']['realtime_info_seen'] ?? ''));
  ?>
  <?php if ($showArt9_1): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;" id="disruptionBlock" data-art="9(1)">
    <strong>⚠️ 7) Afbrydelser/forsinkelser — oplyst før køb (Art. 9 stk. 1)</strong>
    <div class="small" style="margin-top:6px;">1. Var der meddelt afbrydelse/forsinkelse før dit køb?</div>
    <div class="small" style="margin-top:4px;">
      <label class="mr8"><input type="radio" name="preinformed_disruption" value="yes" <?= $pid==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="preinformed_disruption" value="no" <?= $pid==='no'?'checked':'' ?> /> Nej</label>
      <label class="mr8"><input type="radio" name="preinformed_disruption" value="unknown" <?= ($pid===''||$pid==='unknown')?'checked':'' ?> /> Ved ikke</label>
    </div>
    <div id="disQ2" class="small" style="margin-top:8px; display:<?= $pid==='yes'?'block':'none' ?>;">
      <div>2. Hvis ja: Hvor blev det vist?</div>
      <select name="preinfo_channel">
        <option value="" <?= $pic===''?'selected':'' ?>>— vælg —</option>
        <option value="journey_planner" <?= $pic==='journey_planner'?'selected':'' ?>>Rejseplan</option>
        <option value="operator_site_app" <?= $pic==='operator_site_app'?'selected':'' ?>>Operatør-site/app</option>
        <option value="ticket_overview" <?= $pic==='ticket_overview'?'selected':'' ?>>Billetoverblik</option>
        <option value="other" <?= $pic==='other'?'selected':'' ?>>Andet</option>
      </select>
    </div>
    <?php if ($art10Applies): ?>
    <div id="disQ3" class="small" style="margin-top:8px; display:<?= $pid==='yes'?'block':'none' ?>;">
      <div>3. Så du realtime-opdateringer under rejsen?</div>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="app" <?= $ris==='app'?'checked':'' ?> /> Ja, i app</label>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="on_train" <?= $ris==='on_train'?'checked':'' ?> /> Ja, i toget</label>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="station" <?= $ris==='station'?'checked':'' ?> /> Ja, på station</label>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="no" <?= $ris==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <?php endif; ?>
    <div id="disruptionReqError" class="small" style="margin-top:8px; color:#b33; display:none;">Udfyld venligst punkt 7: marker om der var oplyst forsinkelse før køb<?= $art10Applies ? ' (og besvar opfølgning)' : '' ?>.</div>
  </div>
  <?php else: ?>
    <div class="small" style="margin-top:12px; background:#f6f7f9; border:1px solid #e2e6ea; padding:6px; border-radius:6px;">Oplysningspligt før køb (Art. 9 stk. 1) er undtaget — vi behøver ikke disse svar.</div>
  <?php endif; ?>

  <?php
    // Minimal Art. 12 questions inline in TRIN 3 (PGR only)
    // Show only if evaluator suggests missing basics or if values are unknown
    $a12hooks = (array)($art12['hooks'] ?? []);
    $a12missing = (array)($art12['missing'] ?? []);
    $norm = function($v){ $s=strtolower((string)$v); if(in_array($s,['ja','yes','y','1','true'],true)) return 'yes'; if(in_array($s,['nej','no','n','0','false'],true)) return 'no'; if($s===''||$s==='-'||$s==='unknown'||$s==='ved ikke') return 'unknown'; return $s; };
    $scnVal = $norm($meta['separate_contract_notice'] ?? ($a12hooks['separate_contract_notice'] ?? 'unknown'));
    $ttdVal = $norm($meta['through_ticket_disclosure'] ?? ($a12hooks['through_ticket_disclosure'] ?? 'unknown'));
    // Seller channel inference from meta hooks
    $sellerInf = 'unknown';
    $sto = $norm($meta['seller_type_operator'] ?? '');
    $sta = $norm($meta['seller_type_agency'] ?? '');
    if ($sto==='yes') $sellerInf = 'operator'; elseif ($sta==='yes') $sellerInf = 'retailer';
    // Same transaction inference when multiple PNRs
    $stOp = $norm($meta['single_txn_operator'] ?? '');
    $stRt = $norm($meta['single_txn_retailer'] ?? '');
    $sameTxnInf = ($stOp==='yes'||$stRt==='yes') ? 'yes' : (($stOp==='no'&&$stRt==='no') ? 'no' : 'unknown');
    // Gate visibility: show if evaluator is missing any of these, or if values are unknown
    $needA12 = in_array('separate_contract_notice', $a12missing, true) || in_array('through_ticket_disclosure', $a12missing, true)
      || $scnVal==='unknown' || $ttdVal==='unknown' || $sellerInf==='unknown';
    // Also compute PNR count + shared scope hint for same-transaction prompt
    $pnrCountInline = 0; try {
      $pnrSet = [];
      $br = (string)($journey['bookingRef'] ?? ''); if ($br!=='') { $pnrSet[$br]=true; }
      foreach ((array)($groupedTickets ?? []) as $g) { $p=(string)($g['pnr'] ?? ''); if ($p!=='') { $pnrSet[$p]=true; } }
      $pnrCountInline = count($pnrSet);
    } catch (\Throwable $e) { $pnrCountInline = 0; }
  ?>
  <?php if ($needA12): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;" id="art12MinimalBlock" data-art="12">
    <strong>🔗 Art. 12 · Kontraktoplysninger (TRIN 3)</strong>
    <div class="small" style="margin-top:6px;">Hvem solgte dig hele rejsen?</div>
    <div class="small" style="margin-top:4px;">
      <label class="mr8"><input type="radio" name="seller_channel" value="operator" <?= $sellerInf==='operator'?'checked':'' ?> /> Operatør (jernbane)</label>
      <label class="mr8"><input type="radio" name="seller_channel" value="retailer" <?= $sellerInf==='retailer'?'checked':'' ?> /> Forhandler/rejsebureau</label>
      <label class="mr8"><input type="radio" name="seller_channel" value="unknown" /> Ved ikke</label>
    </div>
    <div class="small" style="margin-top:10px;">Blev det oplyst før køb, at billetten var gennemgående?</div>
    <div class="small" style="margin-top:4px;">
      <label class="mr8"><input type="radio" name="through_ticket_disclosure" value="yes" <?= $ttdVal==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="through_ticket_disclosure" value="no" <?= $ttdVal==='no'?'checked':'' ?> /> Nej</label>
      <label class="mr8"><input type="radio" name="through_ticket_disclosure" value="unknown" /> Ved ikke</label>
    </div>
    <div class="small" style="margin-top:10px;">Blev separate kontrakter oplyst?</div>
    <div class="small" style="margin-top:4px;">
      <label class="mr8"><input type="radio" name="separate_contract_notice" value="yes" <?= $scnVal==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="separate_contract_notice" value="no" <?= $scnVal==='no'?'checked':'' ?> /> Nej</label>
      <label class="mr8"><input type="radio" name="separate_contract_notice" value="unknown" /> Ved ikke</label>
    </div>
    <?php $showSameTxn = ($pnrCountInline > 1) || (strtolower((string)($meta['shared_pnr_scope'] ?? '')) === 'no'); ?>
    <?php if ($showSameTxn): ?>
    <div class="small" style="margin-top:10px;">Hvis der er flere PNR'er: Var alle billetter købt i én transaktion?</div>
    <div class="small" style="margin-top:4px;">
      <label class="mr8"><input type="radio" name="same_transaction" value="yes" <?= $sameTxnInf==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="same_transaction" value="no" <?= $sameTxnInf==='no'?'checked':'' ?> /> Nej</label>
      <label class="mr8"><input type="radio" name="same_transaction" value="unknown" /> Ved ikke</label>
    </div>
    <?php endif; ?>
    <div class="small muted" style="margin-top:6px;">(Hjælper med at afgøre om der er gennemgående billet og hvem der er ansvarlig efter Art. 12.)</div>
  </div>
  <?php endif; ?>

  

  <?php if (!empty($meta['_passengers_auto'])): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>📋 Fundne passagerer på billetten</strong>
    <div class="small" style="margin-top:6px;">Redigér navne og markér hvem der klager:</div>
    <div class="small" style="margin-top:6px;">
      <?php $paxList = (array)$meta['_passengers_auto']; ?>
      <?php foreach ($paxList as $i => $p): $nameVal = (string)($p['name'] ?? ''); $age = (string)($p['age_category'] ?? 'unknown'); $isC = !empty($p['is_claimant']); ?>
        <div style="margin-top:6px;">
          <label>Navn
            <input type="text" name="passenger[<?= (int)$i ?>][name]" value="<?= h($nameVal) ?>" placeholder="Passager #<?= (int)($i+1) ?>" />
          </label>
          <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;"><?= h(ucfirst($age)) ?></span>
          <label class="ml8"><input type="checkbox" name="passenger[<?= (int)$i ?>][is_claimant]" value="1" <?= $isC?'checked':'' ?> /> Klager</label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="small" style="margin-top:8px;">
      <label><input type="checkbox" name="claimant_is_legal_representative" value="1" <?= !empty($meta['claimant_is_legal_representative']) ? 'checked' : '' ?> /> Jeg er juridisk værge/ansvarlig for andre på billetten</label>
    </div>
  </div>
  <?php endif; ?>

  <!-- Multi-upload helper replaced by the unified dropzone above -->

  <?php if (!empty($groupedTickets)): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>Billetter samlet i sagen</strong>
    <?php foreach ((array)$groupedTickets as $gi => $g): $shared = !empty($g['shared']); ?>
      <div class="small" style="margin-top:6px;"><strong>Gruppe <?= (int)($gi+1) ?></strong>
        <?php if (!empty($g['pnr']) || !empty($g['dep_date'])): ?>
          (<?= h(trim((string)($g['pnr'] ?? '') . ' ' . (string)($g['dep_date'] ?? ''))) ?>)
        <?php endif; ?>
        <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;"><?= $shared ? 'samlet' : 'enkelt' ?></span>
      </div>
      <ul class="small" style="margin:6px 0 0 16px;">
        <?php foreach ((array)($g['tickets'] ?? []) as $t): ?>
          <li>
            <?= h((string)($t['file'] ?? '')) ?><?= (!empty($t['pnr'])||!empty($t['dep_date'])) ? (': ' . h(trim((string)($t['pnr'] ?? '') . ' ' . (string)($t['dep_date'] ?? '')))) : '' ?>
            <?php $pc = isset($t['passengers']) ? count((array)$t['passengers']) : 0; if ($pc>0): ?>
              <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">pax <?= (int)$pc ?></span>
            <?php endif; ?>
            <?php if (!empty($t['file'])): ?>
              <button type="button" class="small remove-ticket-btn" data-file="<?= h((string)$t['file']) ?>" style="margin-left:6px;">Fjern</button>
            <?php endif; ?>
            <?php if (!empty($t['passengers'])): ?>
              <details style="margin-top:6px;">
                <summary>Redigér passagerer</summary>
                <?php foreach ((array)$t['passengers'] as $i => $p): $nameVal = (string)($p['name'] ?? ''); $age = (string)($p['age_category'] ?? 'unknown'); $isC = !empty($p['is_claimant']); ?>
                  <div style="margin-top:6px;">
                    <label>Navn
                      <input type="text" name="passenger_multi[<?= h((string)$t['file']) ?>][<?= (int)$i ?>][name]" value="<?= h($nameVal) ?>" placeholder="Passager #<?= (int)($i+1) ?>" />
                    </label>
                    <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;"><?= h(ucfirst($age)) ?></span>
                    <label class="ml8"><input type="checkbox" name="passenger_multi[<?= h((string)$t['file']) ?>][<?= (int)$i ?>][is_claimant]" value="1" <?= $isC?'checked':'' ?> /> Klager</label>
                  </div>
                <?php endforeach; ?>
              </details>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
    <div class="small muted" style="margin-top:6px;">Grupperet efter PNR + dato. Du kan uploade flere billetter her.</div>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>3.1. Name of railway undertaking:</strong>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:6px;">
      <label>Operatør
        <input type="text" name="operator" value="<?= h($meta['_auto']['operator']['value'] ?? ($form['operator'] ?? '')) ?>" />
      </label>
      <label>Land
        <input type="text" name="operator_country" value="<?= h($meta['_auto']['operator_country']['value'] ?? ($form['operator_country'] ?? '')) ?>" />
      </label>
      <label>Produkt
        <input type="text" name="operator_product" value="<?= h($meta['_auto']['operator_product']['value'] ?? ($form['operator_product'] ?? '')) ?>" />
      </label>
    </div>
  </div>

  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>3.2. Scheduled journey</strong>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:6px;">
      <label>3.2.1. Departure date (YYYY-MM-DD)
        <input type="text" name="dep_date" value="<?= h($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label>3.2.4. Scheduled time of departure
        <input type="text" name="dep_time" value="<?= h($meta['_auto']['dep_time']['value'] ?? ($form['dep_time'] ?? '')) ?>" placeholder="HH:MM" />
      </label>
      <label>3.2.2. Departure station
        <input type="text" name="dep_station" value="<?= h($meta['_auto']['dep_station']['value'] ?? ($form['dep_station'] ?? '')) ?>" />
      </label>
      <label>3.2.3. Destination station
        <input type="text" name="arr_station" value="<?= h($meta['_auto']['arr_station']['value'] ?? ($form['arr_station'] ?? '')) ?>" />
      </label>
      <label>3.2.5. Scheduled time of arrival
        <input type="text" name="arr_time" value="<?= h($meta['_auto']['arr_time']['value'] ?? ($form['arr_time'] ?? '')) ?>" placeholder="HH:MM" />
      </label>
      <label>3.2.6. Train no./category
        <input type="text" name="train_no" value="<?= h($meta['_auto']['train_no']['value'] ?? ($form['train_no'] ?? '')) ?>" />
      </label>
      <label>3.2.7. Ticket Number(s)/Booking Reference
        <?php
          $ticketNoVal = $meta['_auto']['ticket_no']['value'] ?? ($form['ticket_no'] ?? ($meta['_identifiers']['pnr'] ?? ($journey['bookingRef'] ?? '')));
          if ($ticketNoVal === '' || $ticketNoVal === null) {
            // Fallback: try first grouped ticket PNR
            if (!empty($groupedTickets)) {
              foreach ((array)$groupedTickets as $g) { if (!empty($g['pnr'])) { $ticketNoVal = (string)$g['pnr']; break; } }
            }
          }
        ?>
        <input type="text" name="ticket_no" value="<?= h((string)$ticketNoVal) ?>" />
      </label>
      <label>3.2.8. Ticket price(s)
        <input type="text" name="price" value="<?= h($meta['_auto']['price']['value'] ?? ($form['price'] ?? '')) ?>" placeholder="100 EUR" />
      </label>
      <?php
        // Build journey summary + missed-connection station choices with both arrival and next departure times
  $mcChoicesInline = [];
        $journeyRows = [];
        $changeBullets = [];
        // Prefer segments detected for the current upload; if empty, use first non-empty grouped ticket
        $segAutoInline = (array)($meta['_segments_auto'] ?? []);
        if (empty($segAutoInline)) {
          foreach ((array)($groupedTickets ?? []) as $grp) {
            $gs = (array)($grp['segments'] ?? []);
            if (!empty($gs)) { $segAutoInline = $gs; break; }
          }
        }
  $mctEvalRaw = (array)($meta['_mct_eval'] ?? []);
  $norm = function($s){ return trim(mb_strtolower((string)$s, 'UTF-8')); };
  $mctByStation = [];
  foreach ($mctEvalRaw as $ev) { $mctByStation[$norm($ev['station'] ?? '')] = $ev; }
        $fmtDk = function($dateStr){ if(!$dateStr) return ''; $t = @strtotime($dateStr); if(!$t) return ''; return date('d.m.', $t); };
        $toMin = function($t){ if(!preg_match('/^(\d{1,2}):(\d{2})$/', (string)$t, $m)) return null; return (int)$m[1]*60 + (int)$m[2]; };
        try {
          if (!empty($segAutoInline)) {
            $last = count($segAutoInline) - 1;
            foreach ($segAutoInline as $i => $s) {
              $from = trim((string)($s['from'] ?? ''));
              $to = trim((string)($s['to'] ?? ''));
              $dep = trim((string)($s['schedDep'] ?? ''));
              $arr = trim((string)($s['schedArr'] ?? ''));
              $prod = trim((string)($s['trainNo'] ?? ''));
              $depD = (string)($s['depDate'] ?? ($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')));
              $arrD = (string)($s['arrDate'] ?? $depD);
              $journeyRows[] = [
                'leg' => $from . ' → ' . $to,
                'dep' => ($dep ? ($fmtDk($depD) . ' kl. ' . $dep) : ''),
                'arr' => ($arr ? ($fmtDk($arrD) . ' kl. ' . $arr) : ''),
                'train' => $prod,
                'change' => ($i < $last ? ('Skift i ' . $to) : '— (slutstation)'),
              ];
              if ($i < $last) {
                $next = $segAutoInline[$i+1] ?? [];
                $nextDep = trim((string)($next['schedDep'] ?? ''));
                $toName = $to;
                // Build radio labels with both arr and next dep and layover
                $lay = null; $m1 = $toMin($arr); $m2 = $toMin($nextDep);
                if ($m1 !== null && $m2 !== null) { $lay = $m2 - $m1; if ($lay < 0) { $lay += 24*60; } }
                $label = $toName;
                if ($arr || $nextDep) {
                  $label .= ' (ank. ' . ($arr ?: '—') . ' → afg. ' . ($nextDep ?: '—') . (($lay !== null && $lay >= 0 && $lay <= 360) ? (', ophold ' . $lay . ' min') : '') . ')';
                }
                // Append MCT judgement if available
                $ev = $mctByStation[$norm($toName)] ?? null;
                if (is_array($ev)) {
                  $ok = !empty($ev['realistic']); $thr = (int)($ev['threshold'] ?? 0);
                  $label .= $ok ? ' [MCT ok ≥ ' . $thr . 'm]' : ' [MCT kort < ' . $thr . 'm]';
                }
                // Collect ALL changes without deduping by station to ensure multiple skift are shown
                if (!isset($mcChoicesInline) || !is_array($mcChoicesInline)) { $mcChoicesInline = []; }
                $mcChoicesInline[] = ['station' => $toName, 'label' => $label];
                if ($lay !== null && $lay >= 0 && $lay <= 360) {
                  $bullet = '➡️ i ' . $toName . ' (ankomst ' . $arr . ', afgang ' . $nextDep . '), 🕓 Opholdstid: ' . $lay . ' minutter';
                  if (is_array($ev)) { $bullet .= !empty($ev['realistic']) ? ' — MCT: OK' : ' — MCT: for kort'; }
                  $changeBullets[] = $bullet;
                }
              }
            }
          }
        } catch (\Throwable $e) { /* ignore */ }
        $currentMissInline = (string)($form['missed_connection_station'] ?? '');
      ?>
      <?php
        // Fallback: if no inline candidates but controller built a simple list, use it
        if (empty($mcChoicesInline)) {
          $simple = (array)($form['_miss_conn_choices'] ?? []);
          if (!empty($simple)) {
            // Normalize controller-provided map (station => station) into list of {station,label}
            $mcChoicesInline = [];
            foreach ($simple as $st => $lbl) { $mcChoicesInline[] = ['station' => (string)$st, 'label' => (string)$lbl]; }
          }
        }
      ?>
      <div style="grid-column: 1 / span 2;">
        <label>3.5. Missed connection (kun station)
          <input id="mcField" type="text" name="missed_connection_station" value="<?= h($meta['_auto']['missed_connection_station']['value'] ?? ($form['missed_connection_station'] ?? '')) ?>" placeholder="Skriv skiftestation (hvis relevant)" />
        </label>
        <?php if (!empty($mcChoicesInline)): ?>
          <div class="small muted" style="margin-top:6px;">Vælg hvor skiftet blev misset (enkeltvalg):</div>
          <div class="small" style="margin-top:4px; display:flex; flex-direction:column; gap:6px;">
            <?php foreach ($mcChoicesInline as $opt): $stationOpt = (string)($opt['station'] ?? ''); $labelOpt = (string)($opt['label'] ?? $stationOpt); $checked = (string)$currentMissInline === (string)$stationOpt; ?>
              <label class="mr8"><input type="radio" name="missed_connection_pick" value="<?= h($stationOpt) ?>" <?= $checked?'checked':'' ?> data-mc-single data-station="<?= h($stationOpt) ?>" /> <?= h($labelOpt) ?></label>
            <?php endforeach; ?>
          </div>
          <script>
            (function(){
              var radios = document.querySelectorAll('input[type="radio"][data-mc-single]');
              var field = document.getElementById('mcField');
              // Mark initial state
              radios.forEach(function(r){ r.dataset.selected = r.checked ? '1' : '0'; });
              // Toggle-on-click behavior: clicking the currently selected radio unchecks it and clears the field
              radios.forEach(function(r){
                r.addEventListener('click', function(ev){
                  if (r.dataset.selected === '1') {
                    // Deselect
                    r.checked = false;
                    r.dataset.selected = '0';
                    if (field) { field.value = ''; }
                    // Prevent the default selection re-apply
                    ev.preventDefault();
                    return false;
                  }
                  // Select this and unselect others
                  radios.forEach(function(o){ o.dataset.selected = '0'; });
                  r.dataset.selected = '1';
                  if (field) { field.value = (r.getAttribute('data-station') || r.value); }
                });
                // Also update field on change (keyboard navigation)
                r.addEventListener('change', function(){ if (r.checked && field) { field.value = (r.getAttribute('data-station') || r.value); } });
              });
            })();
          </script>
        <?php else: ?>
          <div class="small muted" style="margin-top:6px;">Ingen skift fundet automatisk. Hvis du missede en forbindelse, skriv stationen manuelt ovenfor.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($journeyRows)): ?>
      <div class="small" style="margin-top:10px;"><strong>Rejseplan (aflæst fra billetten)</strong></div>
      <div class="small" style="overflow:auto;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Strækning</th>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Afgang</th>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Ankomst</th>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Tog</th>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Skift</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($journeyRows as $r): ?>
              <tr>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['leg']) ?></td>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['dep']) ?></td>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['arr']) ?></td>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['train']) ?></td>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['change']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($changeBullets)): ?>
        <div class="small" style="margin-top:8px;">
          <div><strong>📍 Der er <?= count($changeBullets) ?> skift<?= count($changeBullets)===1?'':'e' ?>:</strong></div>
          <ul style="margin:6px 0 0 16px;">
            <?php foreach ($changeBullets as $b): ?>
              <li><?= h($b) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if (empty($mcChoicesInline)): ?>
        <div class="small muted" style="margin-top:8px;">Ingen skift fundet — punkt 3.5 vises kun, når der er et skift i rejsen.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php
    // Show SE regional distance toggle to drive exemptions under 150 km
    $scopeNow = (string)($profile['scope'] ?? '');
    $countryNow = strtoupper((string)($journey['country']['value'] ?? ($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))));
    $showSE150 = ($countryNow === 'SE' && $scopeNow === 'regional');
  ?>
  <?php if ($showSE150): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>SE-specifik undtagelse</strong>
    <div class="small" style="margin-top:6px;">Gælder kun for regionale rejser under 150 km.</div>
    <label class="small" style="margin-top:6px; display:inline-block;">
      <input type="checkbox" name="se_under_150km" value="1" <?= !empty($journey['se_under_150km']) ? 'checked' : '' ?> onchange="this.form.submit()" /> Strækningen er under 150 km
    </label>
  </div>
  <?php endif; ?>

  

  <div class="actions-row" style="display:flex; gap:8px; align-items:center; margin-top:12px;">
    <a href="<?= $this->Url->build(['action' => 'journey']) ?>" class="button" style="background:#eee; color:#333;">Tilbage</a>
    <button type="submit" name="continue" value="1" class="button">Fortsæt</button>
    <!-- Removed duplicate 'Kendt før køb?' checkbox; use Section 7 radios above (preinformed_disruption) -->
  </div>
<?= $this->Form->end() ?>

<?php if ($this->getRequest()->getQuery('debug')): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #eef; background:#f9fbff; border-radius:6px;">
    <strong>Debug</strong>
    <div class="small" style="margin-top:6px;">EU only (anbefalet): <code><?= h((string)($euOnlySuggested ?? 'unknown')) ?></code></div>
    <?php if (!empty($euOnlyReason)): ?><div class="small" style="margin-top:6px;">Begrundelse: <?= h($euOnlyReason) ?></div><?php endif; ?>

    <?php
      $segAutoDbg = (array)($meta['_segments_auto'] ?? []);
      $segLlmSuggestDbg = (array)($meta['_segments_llm_suggest'] ?? []);
      $segDbg = (array)($meta['_segments_debug'] ?? []);
      $logsDbg = (array)($meta['logs'] ?? []);
      $ocrText = (string)($meta['_ocr_text'] ?? '');
    ?>
    <div class="small" style="margin-top:10px;"><strong>🔎 Parser/segments</strong></div>
    <div class="small" style="margin-top:4px;">Segments auto: <code><?= (int)count($segAutoDbg) ?></code></div>
    <?php if (!empty($segAutoDbg)): ?>
      <ul class="small" style="margin-top:4px; padding-left:16px;">
        <?php foreach (array_slice($segAutoDbg, 0, 5) as $s): $from=(string)($s['from']??''); $to=(string)($s['to']??''); $d=(string)($s['schedDep']??''); $a=(string)($s['schedArr']??''); ?>
          <li><?= h(trim($from . ' → ' . $to)) ?> <?= h(trim($d . ($a!==''?'–'.$a:''))) ?></li>
        <?php endforeach; ?>
        <?php if (count($segAutoDbg) > 5): ?><li>…</li><?php endif; ?>
      </ul>
    <?php endif; ?>

    <?php if (!empty($segLlmSuggestDbg)): ?>
      <div class="small" style="margin-top:8px;">LLM forslag til segments: <code><?= (int)count($segLlmSuggestDbg) ?></code></div>
      <ul class="small" style="margin-top:4px; padding-left:16px;">
        <?php foreach (array_slice($segLlmSuggestDbg, 0, 5) as $s): $from=(string)($s['from']??''); $to=(string)($s['to']??''); $d=(string)($s['schedDep']??''); $a=(string)($s['schedArr']??''); ?>
          <li><?= h(trim($from . ' → ' . $to)) ?> <?= h(trim($d . ($a!==''?'–'.$a:''))) ?></li>
        <?php endforeach; ?>
        <?php if (count($segLlmSuggestDbg) > 5): ?><li>…</li><?php endif; ?>
      </ul>
    <?php endif; ?>

    <?php if (!empty($logsDbg)): ?>
      <div class="small" style="margin-top:8px;"><strong>Logs</strong></div>
      <ul class="small" style="margin-top:4px; padding-left:16px;">
        <?php foreach ($logsDbg as $ln): ?>
          <li><?= h((string)$ln) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($ocrText !== ''): ?>
      <details style="margin-top:8px;">
        <summary class="small"><strong>OCR (første 600 tegn)</strong></summary>
        <pre style="white-space:pre-wrap; background:#f3f6ff; padding:6px; border-radius:6px; max-height:240px; overflow:auto;"><?= h(mb_substr($ocrText, 0, 600, 'UTF-8')) ?></pre>
      </details>
    <?php endif; ?>

    <?php if (!empty($segDbg)): ?>
      <details style="margin-top:8px;">
        <summary class="small"><strong>Segments debug (detaljer)</strong></summary>
        <pre style="white-space:pre-wrap; background:#f3f6ff; padding:6px; border-radius:6px; max-height:240px; overflow:auto;"><?= h(json_encode($segDbg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      </details>
    <?php endif; ?>
    <div class="small" style="margin-top:6px;">Art. 12</div>
    <pre style="white-space:pre-wrap; background:#f3f6ff; padding:6px; border-radius:6px; max-height:240px; overflow:auto;"><?= h(json_encode($art12, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    <div class="small" style="margin-top:6px;">Art. 9 (on request)</div>
    <?php if (!empty($compute['art9OptIn'])): ?>
      <pre style="white-space:pre-wrap; background:#f3f6ff; padding:6px; border-radius:6px; max-height:240px; overflow:auto;"><?= h(json_encode($art9, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php else: ?>
      <p class="small">Art. 9 vises kun hvis du markerer boksen.</p>
    <?php endif; ?>
    <div class="small" style="margin-top:6px;">Refusion: <?= !empty($refund['eligible']) ? 'Mulig' : 'Ikke mulig' ?>, Assistance: <?= h(implode(', ', (array)($refusion['options'] ?? []))) ?></div>
  </div>
<?php endif; ?>

  <div class="card" style="position:sticky; top:12px; margin-top:12px; padding:12px; border:1px solid #ddd; background:#f9fbff; border-radius:6px; max-height:80vh; overflow:auto;">
  <div id="hooksPanel">
    <?= $this->element('hooks_panel', compact('profile','art12','art9','refund','refusion','form','meta','groupedTickets','euOnlySuggested','euOnlyReason','journey','formDecision') + ['showFormDecision' => true, 'showArt12Section' => false]) ?>
  </div>
  <div class="small muted" style="margin-top:6px;">Hooks-panel opdateres automatisk ved ændringer.</div>
  <div class="small" style="margin-top:6px; display:flex; gap:8px; align-items:center;">
    <a href="<?= $this->Url->build($this->getRequest()->getPath() . '?debug=1') ?>">Vis mere debug</a>
    <label style="margin-left:auto;"><input type="checkbox" id="toggleDebugChk" <?= $this->getRequest()->getQuery('debug') ? 'checked' : '' ?> /> Debug</label>
  </div>
  </div>

<?php
// If Art. 12 does not apply (no through-ticket), display per-contract table (TRIN 3 grouping)
$a12Applies = isset($art12['art12_applies']) ? (bool)$art12['art12_applies'] : null;
if ($a12Applies === false && !empty($contractsView)) {
  echo $this->element('per_contract_table', compact('contractsView'));
}
?>

<script>
(function(){
  const form = document.getElementById('entitlementsForm');
  const panel = document.getElementById('hooksPanel');
  if (!form || !panel) return;
  // Upload UI wiring
  const drop = document.getElementById('uploadDropzone');
  const addBtn = document.getElementById('addFilesBtn');
  const inputMulti = document.getElementById('ticketMulti');
  const inputSingle = document.getElementById('ticketSingle');
  const list = document.getElementById('selectedFilesList');
  const clearBtn = document.getElementById('clearFilesBtn');
  let dt = new DataTransfer();

  function fileKey(f){ return [f.name, f.size, f.lastModified].join(':'); }
  function addFiles(files){
    const seen = new Set(Array.from(dt.files).map(fileKey));
    for (const f of files) { if (!seen.has(fileKey(f))) dt.items.add(f); }
    sync();
  }
  function removeIndex(idx){
    const ndt = new DataTransfer();
    Array.from(dt.files).forEach((f,i)=>{ if(i!==idx) ndt.items.add(f); });
    dt = ndt; sync();
  }
  function sync(){
    inputMulti.files = dt.files;
    const sdt = new DataTransfer();
    if (dt.files.length > 0) sdt.items.add(dt.files[0]);
    inputSingle.files = sdt.files;
    renderList();
    // Auto-submit after a micro delay so UI updates first
    setTimeout(()=>form.submit(), 0);
  }
  function renderList(){
    list.innerHTML = '';
    if (dt.files.length === 0) {
      const li = document.createElement('li');
      li.className = 'muted';
      li.textContent = 'Der er ikke valgt nogen fil.';
      list.appendChild(li);
      return;
    }
    Array.from(dt.files).forEach((f, i)=>{
      const li = document.createElement('li');
      li.style.display = 'flex'; li.style.alignItems = 'center'; li.style.gap = '8px'; li.style.marginTop = '6px';
      const name = document.createElement('span');
      name.textContent = f.name + ' (' + Math.round(f.size/1024) + ' KB)';
      const rm = document.createElement('button');
      rm.type = 'button'; rm.className = 'small'; rm.textContent = 'Fjern';
      rm.addEventListener('click', ()=> removeIndex(i));
      li.appendChild(name); li.appendChild(rm);
      list.appendChild(li);
    });
  }
  if (drop && addBtn && inputMulti && inputSingle && list) {
  drop.addEventListener('click', ()=> inputMulti.click());
  addBtn.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); inputMulti.click(); });
    if (clearBtn) clearBtn.addEventListener('click', (e)=>{
      e.preventDefault(); e.stopPropagation();
      // Reset client-side selection
      dt = new DataTransfer();
      // Mark server-side clear-all and submit
      const hid = document.createElement('input');
      hid.type = 'hidden'; hid.name = 'clear_all'; hid.value = '1';
      form.appendChild(hid);
      sync();
    });
    drop.addEventListener('dragover', (e)=>{ e.preventDefault(); drop.style.background='#eef7ff'; });
    drop.addEventListener('dragleave', ()=>{ drop.style.background='#f7fbff'; });
    drop.addEventListener('drop', (e)=>{ e.preventDefault(); drop.style.background='#f7fbff'; if (e.dataTransfer?.files?.length){ addFiles(e.dataTransfer.files); }});
    inputMulti.addEventListener('change', ()=>{ if (inputMulti.files?.length) addFiles(inputMulti.files); });
    renderList();
  }

  // Always use partial AJAX refresh on input/change (PGR flow)
  let t = null;
  const trigger = () => {
    if (t) clearTimeout(t);
    t = setTimeout(async () => {
      try {
        const fd = new FormData(form);
        const url = new URL(window.location.href);
        url.searchParams.set('ajax_hooks','1');
        const tokenInput = form.querySelector('input[name="_csrfToken"]');
        const csrf = tokenInput ? tokenInput.value : '';
        const res = await fetch(url.toString(), {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
          credentials: 'same-origin',
          body: fd
        });
        if (!res.ok) return;
        const html = await res.text();
        panel.innerHTML = html;
      } catch (e) { /* ignore network errors for now */ }
    }, 300);
  };
  form.addEventListener('input', trigger, { passive: true });
  form.addEventListener('change', trigger, { passive: true });

  // Sync missed-connection radios to text field
  const mcField = document.getElementById('mcField');
  const mcRadios = Array.from(document.querySelectorAll('input[type="radio"][data-mc-single]'));
  const syncMcSingle = () => {
    if (!mcField || !mcRadios.length) return;
    const sel = mcRadios.find(r=>r.checked);
    mcField.value = sel ? sel.value : '';
  };
  if (mcRadios.length) {
    mcRadios.forEach(r=> r.addEventListener('change', syncMcSingle));
    syncMcSingle();
  }

  // Bike flow client-side visibility for smoother UX
  function val(name){
    const nodes = form.querySelectorAll('input[name="'+name+'"]');
    for (const n of nodes) { if ((n.type==='radio'||n.type==='checkbox') && n.checked) return n.value; }
    return '';
  }
  function show(el, on){ if (!el) return; el.style.display = on ? 'block' : 'none'; }
  function updateBike(){
    const q2 = document.getElementById('bikeQ2');
    const block = document.getElementById('bikeArticle6');
    const q3b = document.getElementById('bikeQ3B');
    const q5 = document.getElementById('bikeQ5');
    const q6 = document.getElementById('bikeQ6');
    const was = val('bike_was_present') || '<?= $w??'' ?>';
    const cause = val('bike_caused_issue') || '<?= $bikeCause??'' ?>';
    const resMade = val('bike_reservation_made') || '<?= $bikeResMade??'' ?>';
    const denied = val('bike_denied_boarding') || '<?= $bikeDenied??'' ?>';
    const reasonProv = val('bike_refusal_reason_provided') || '<?= $bikeReasonProv??'' ?>';
    show(q2, was==='yes');
    show(block, was==='yes' && cause==='yes');
    show(q3b, resMade==='no');
    show(q5, denied==='yes');
    show(q6, denied==='yes' && reasonProv==='yes');
  }
  form.addEventListener('change', (e)=>{
    if (!e.target) return;
    const nm = e.target.name||'';
    if (nm.startsWith('bike_')) { updateBike(); }
  }, { passive:true });
  // Initial
  updateBike();

  // Klasse & reservation: intelligent trinvis visning
  function updateClassUI(){
    const q2 = document.getElementById('classQ2');
    const q4 = document.getElementById('classQ4');
    const selClass = form.querySelector('select[name="fare_class_purchased"]');
    const selRes = form.querySelector('select[name="berth_seat_type"]');
    const classVal = (selClass && selClass.value) ? selClass.value : '<?= $fcp ?>';
    const resVal = (selRes && selRes.value) ? selRes.value : '<?= $bst ?>';
    const showQ2 = !!classVal && classVal !== 'unknown';
    const needsDelivery = ['seat','couchette','sleeper'].includes(resVal||'');
    if (q2) q2.style.display = showQ2 ? 'block' : 'none';
    if (q4) q4.style.display = needsDelivery ? 'block' : 'none';
  }
  form.addEventListener('change', (e)=>{
    const nm = (e.target && (e.target.name||'')) || '';
    if (nm === 'fare_class_purchased' || nm === 'berth_seat_type') updateClassUI();
    if (nm === 'fare_flex_type') updateSeasonUI();
  }, { passive:true });
  updateClassUI();

  // Season/period pass: show details block when dropdown is "pass"
  function updateSeasonUI(){
    const sel = form.querySelector('select[name="fare_flex_type"]');
    const block = document.getElementById('seasonPassBlock');
    const has = sel && sel.value === 'pass';
    if (block) block.style.display = has ? 'block' : 'none';
    // Maintain a hidden flag so server can persist even on AJAX refreshes
    let hid = form.querySelector('input[name="season_pass_has"]');
    if (!hid) { hid = document.createElement('input'); hid.type = 'hidden'; hid.name = 'season_pass_has'; form.appendChild(hid); }
    hid.value = has ? '1' : '';
  }
  updateSeasonUI();

  // Debug checkbox toggles ?debug=1 in the URL for more hooks info
  const dbg = document.getElementById('toggleDebugChk');
  if (dbg) {
    dbg.addEventListener('change', ()=>{
      const url = new URL(window.location.href);
      if (dbg.checked) { url.searchParams.set('debug','1'); }
      else { url.searchParams.delete('debug'); }
      // Preserve PRG mode flag in URL
      try {
        const prgNow = new URLSearchParams(window.location.search).get('prg') || new URLSearchParams(window.location.search).get('pgr');
        if (prgNow) { url.searchParams.set('prg','1'); }
      } catch(e) {}
      window.location.assign(url.toString());
    });
  }
  // Handle ticket removal without nested forms (keeps main form valid)
  const rmBtns = Array.from(document.querySelectorAll('.remove-ticket-btn'));
  if (rmBtns.length) {
    rmBtns.forEach(btn => {
      btn.addEventListener('click', (e)=>{
        e.preventDefault();
        const v = btn.getAttribute('data-file') || '';
        if (!v) return;
        const hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'remove_ticket';
        hid.value = v;
        form.appendChild(hid);
        form.submit();
      });
    });
  }

  // PMR client-side visibility
  function valRadio(name){
    const els = form.querySelectorAll('input[name="'+name+'"]');
    for (const el of els) { if (el.checked) return el.value; }
    return '';
  }
  function updatePMR(){
    const qBooked = document.getElementById('pmrQBooked');
    const qDelivered = document.getElementById('pmrQDelivered');
    const qProm = document.getElementById('pmrQPromised');
    const qDet = document.getElementById('pmrQDetails');
    const u = valRadio('pmr_user');
    const b = valRadio('pmr_booked');
    const p = valRadio('pmr_promised_missing');
    if (qBooked) qBooked.style.display = (u==='yes') ? 'block' : 'none';
    if (qDelivered) qDelivered.style.display = (u==='yes' && b!=='no') ? 'block' : 'none';
    if (qProm) qProm.style.display = (u==='yes') ? 'block' : 'none';
    if (qDet) qDet.style.display = (p==='yes') ? 'block' : 'none';
  }
  form.addEventListener('change', (e)=>{
    const nm = (e.target && e.target.name) || '';
    if (nm.startsWith('pmr_')) updatePMR();
    if (nm === 'preinformed_disruption' || nm === 'preinfo_channel' || nm === 'realtime_info_seen') updateDisruption();
  }, { passive:true });
  updatePMR();

  // Disruption client-side visibility + simple required checks for Continue
  const art10Applies = <?= json_encode((bool)($profile['articles']['art10'] ?? true)) ?>;
  function valRadio2(name){ const els = form.querySelectorAll('input[name="'+name+'"]'); for (const el of els) { if (el.checked) return el.value; } return ''; }
  function updateDisruption(){
    const q2 = document.getElementById('disQ2');
    const q3 = document.getElementById('disQ3');
    const q1 = valRadio2('preinformed_disruption');
    if (q2) q2.style.display = (q1==='yes') ? 'block' : 'none';
    if (q3) q3.style.display = (q1==='yes' && art10Applies) ? 'block' : 'none';
  }
  updateDisruption();

  // Only enforce disruption answers (Art. 9(1)) when that article is actually applicable/visible
  const art9_1_enforced = <?= json_encode($showArt9_1) ?>;
  const disruptionBlock = document.getElementById('disruptionBlock');
  const contBtn = form.querySelector('button[name="continue"]');
  if (contBtn) {
    contBtn.addEventListener('click', (e)=>{
      // Skip gating entirely when Art. 9(1) is exempt/hidden
      if (!art9_1_enforced || !disruptionBlock) { return; }
      const err = document.getElementById('disruptionReqError');
      if (err) err.style.display = 'none';
      const q1 = valRadio2('preinformed_disruption');
      if (!q1) {
        e.preventDefault();
        if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        return;
      }
      if (q1 === 'yes') {
        const sel = form.querySelector('select[name="preinfo_channel"]');
        if (sel && !sel.value) {
          e.preventDefault();
          if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
          return;
        }
        if (art10Applies) {
          const q3 = valRadio2('realtime_info_seen');
          if (!q3) {
            e.preventDefault();
            if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            return;
          }
        }
      }
    });
  }
})();
</script>
