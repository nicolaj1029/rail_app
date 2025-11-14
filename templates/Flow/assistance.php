<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$profile = $profile ?? ['articles' => []];
$isCompleted = (!empty($flags['travel_state']) && $flags['travel_state'] === 'completed');
$assistOff = isset($profile['articles']['art20_2']) && $profile['articles']['art20_2'] === false;
$reason_delay = !empty($incident['main']) && $incident['main'] === 'delay';
$reason_cancellation = !empty($incident['main']) && $incident['main'] === 'cancellation';
$reason_missed_conn = !empty($incident['missed']);
?>

<style>
  .hidden { display:none; }
  .small { font-size:12px; }
  .muted { color:#666; }
  .hl { background:#fff3cd; padding:6px; border-radius:6px; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; }
</style>

<h1>TRIN 5 · Assistance og udgifter (Art. 20)</h1>
<?= $this->Form->create(null, ['type' => 'file']) ?>

<fieldset class="fieldset mt12">
  <legend>Art. 20 · Assistance og udgifter</legend>
  <div class="small muted">Aktiveres ved forsinkelse ≥60 min, aflysning eller afbrudt forbindelse. Ekstraordinære forhold påvirker kun hotel-loft (max 3 nætter).</div>

  <?php if ($isCompleted): ?>
    <div class="card">
      <strong>Rejsen er afsluttet — hvad blev tilbudt/leveret mens du ventede?</strong>
      <?php $rem = (string)($form['remedyChoice'] ?? ''); ?>
      <div class="mt8" id="assistNotesPast">
        <div id="assistNoteRefundPast" class="<?= $rem==='refund_return' ? '' : 'hidden' ?> small hl">Rettigheder (ved refusion): Mad og drikke mens du venter; hotel hvis retur ikke var mulig samme dag; ret til returtransport.</div>
        <div id="assistNoteSoonestPast" class="<?= $rem==='reroute_soonest' ? '' : 'hidden' ?> small hl">Rettigheder (ved omlægning hurtigst muligt): Mad og drikke mens du venter; hotel hvis næste tog først næste dag.</div>
        <div id="assistNoteLaterPast" class="<?= $rem==='reroute_later' ? '' : 'hidden' ?> small hl">Rettigheder (omlægning senere efter ønske): Kun i den oprindelige forsinkelsesperiode indtil du traf dit valg.</div>
      </div>

      <?php if (!$assistOff): ?>
      <div id="assistA_past">
        <div class="mt8"><strong>A) Tilbudt assistance</strong></div>
        <?php $mo = (string)($form['meal_offered'] ?? ''); ?>
        <div class="mt4">1. Fik du måltider/forfriskninger under ventetiden? (Art. 20(2)(a))</div>
        <label><input type="radio" name="meal_offered" value="yes" <?= $mo==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="meal_offered" value="no" <?= $mo==='no'?'checked':'' ?> /> Nej</label>

        <?php $ho = (string)($form['hotel_offered'] ?? ''); ?>
        <div class="mt8">2. Fik du hotel/indkvartering + transport dertil? (Art. 20(2)(b))</div>
        <label><input type="radio" name="hotel_offered" value="yes" <?= $ho==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="hotel_offered" value="no" <?= $ho==='no'?'checked':'' ?> /> Nej</label>
        <?php $on = (string)($form['overnight_needed'] ?? ''); ?>
        <div class="mt4 <?= $ho==='no' ? '' : 'hidden' ?>" id="overnightWrapPast">
          <span>Hvis nej: Blev overnatning nødvendig?</span>
          <label class="ml8"><input type="radio" name="overnight_needed" value="yes" <?= $on==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="overnight_needed" value="no" <?= $on==='no'?'checked':'' ?> /> Nej</label>
          <div class="small muted mt4">Ved ekstraordinære forhold kan hotel begrænses til 3 nætter.</div>
        </div>

        <?php $bt = (string)($form['blocked_train_alt_transport'] ?? ''); ?>
        <div class="mt8">3. Var toget blokeret på sporet — fik du transport væk? (Art. 20(2)(c))</div>
        <label><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <?php else: ?>
        <div class="small mt8 hl">⚠️ Assistance efter Art. 20(2) (måltider/hotel/evakuering) er markeret som undtaget for denne rejse. Udfyld i stedet dine udgifter nedenfor, så behandles de som refusion efter Art. 18(3).</div>
      <?php endif; ?>

      <div class="mt12"><strong>B) Alternative transporttjenester</strong></div>
      <?php $ap = (string)($form['alt_transport_provided'] ?? ''); ?>
      <div>4. Fik du alternative transporttjenester, hvis forbindelsen blev afbrudt? (Art. 20(3))</div>
      <label><input type="radio" name="alt_transport_provided" value="yes" <?= $ap==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="alt_transport_provided" value="no" <?= $ap==='no'?'checked':'' ?> /> Nej</label>

      <fieldset class="fieldset mt12">
        <legend>Dine udgifter (udfyld kun hvis du selv betalte)</legend>
        <div class="small muted">Disse udgifter behandles som refusion af nødvendige udlæg efter Art. 18(3), når vederlagsfri hjælp efter Art. 20 ikke blev leveret.</div>
        <!-- Måltider egenbetaling -->
        <div class="mt8" <?= $assistOff ? '' : 'data-reveal="meal_offered:no"' ?>>
          <label>Måltider – beløb
            <input type="number" step="0.01" name="meal_self_paid_amount" value="<?= h($form['meal_self_paid_amount'] ?? '') ?>" />
          </label>
          <label class="ml8">Valuta
            <input type="text" name="meal_self_paid_currency" value="<?= h($form['meal_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? '')) ?>" placeholder="EUR" />
          </label>
          <div class="mt4">
            <label class="small">Upload kvittering (PDF/JPG/PNG)
              <input type="file" name="meal_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
            </label>
            <?php $mru = (string)($form['meal_self_paid_receipt'] ?? ''); if ($mru !== ''): ?>
              <div class="small muted mt4">Uploadet: <?= h(basename($mru)) ?></div>
            <?php endif; ?>
          </div>
        </div>
  <!-- Hotel egenbetaling -->
  <div class="mt8" <?= $assistOff ? '' : 'data-reveal="hotel_offered:no"' ?>>
          <label>Hotel – beløb (samlet)
            <input type="number" step="0.01" name="hotel_self_paid_amount" value="<?= h($form['hotel_self_paid_amount'] ?? '') ?>" />
          </label>
          <label class="ml8">Valuta
            <input type="text" name="hotel_self_paid_currency" value="<?= h($form['hotel_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? '')) ?>" placeholder="EUR" />
          </label>
          <label class="ml8">Antal nætter
            <input type="number" step="1" name="hotel_self_paid_nights" value="<?= h($form['hotel_self_paid_nights'] ?? '') ?>" />
          </label>
          <div class="mt4">
            <label class="small">Upload kvittering (PDF/JPG/PNG)
              <input type="file" name="hotel_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
            </label>
            <?php $hru = (string)($form['hotel_self_paid_receipt'] ?? ''); if ($hru !== ''): ?>
              <div class="small muted mt4">Uploadet: <?= h(basename($hru)) ?></div>
            <?php endif; ?>
          </div>
        </div>
  <!-- Evakuering egenbetaling -->
  <div class="mt8" <?= $assistOff ? '' : 'data-reveal="blocked_train_alt_transport:no"' ?>>
          <label>Transport væk – beløb
            <input type="number" step="0.01" name="blocked_self_paid_amount" value="<?= h($form['blocked_self_paid_amount'] ?? '') ?>" />
          </label>
          <label class="ml8">Valuta
            <input type="text" name="blocked_self_paid_currency" value="<?= h($form['blocked_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? '')) ?>" placeholder="EUR" />
          </label>
          <div class="mt4">
            <label class="small">Upload kvittering (PDF/JPG/PNG)
              <input type="file" name="blocked_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
            </label>
            <?php $bru = (string)($form['blocked_self_paid_receipt'] ?? ''); if ($bru !== ''): ?>
              <div class="small muted mt4">Uploadet: <?= h(basename($bru)) ?></div>
            <?php endif; ?>
          </div>
        </div>
  <!-- Alternativ transport egenbetaling -->
  <div class="mt8" data-reveal="alt_transport_provided:no">
          <label>Alternativ transport til destination – beløb
            <input type="number" step="0.01" name="alt_self_paid_amount" value="<?= h($form['alt_self_paid_amount'] ?? '') ?>" />
          </label>
          <label class="ml8">Valuta
            <input type="text" name="alt_self_paid_currency" value="<?= h($form['alt_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? '')) ?>" placeholder="EUR" />
          </label>
          <div class="mt4">
            <label class="small">Upload kvittering (PDF/JPG/PNG)
              <input type="file" name="alt_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
            </label>
            <?php $aru = (string)($form['alt_self_paid_receipt'] ?? ''); if ($aru !== ''): ?>
              <div class="small muted mt4">Uploadet: <?= h(basename($aru)) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="small muted mt4">Tip: Angiv en fælles valuta under "Dokumentation & udgifter", feltet "Valuta". Den bruges som fallback her.</div>
      </fieldset>

      <!-- Removed legacy C) Dokumentation & udgifter block; per-field uploads are provided below each beløbfelt -->

      

      

      <?php if ($assistOff): ?>
        <div class="small mt8 hl">⚠️ Assistance (måltider/hotel/transport) kan være undtaget her. Vi logger dine udgifter og rejser krav efter lokale regler/kontraktvilkår.</div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="card">
      <strong>Rejsen er ikke afsluttet — hvad får du tilbudt mens du venter?</strong>
      <?php $rem = (string)($form['remedyChoice'] ?? ''); ?>
      <div class="mt8" id="assistNotesNow">
        <div id="assistNoteRefundNow" class="<?= $rem==='refund_return' ? '' : 'hidden' ?> small hl">Rettigheder (ved refusion): Mad og drikke mens du venter; hotel hvis retur ikke er mulig samme dag; ret til returtransport.</div>
        <div id="assistNoteSoonestNow" class="<?= $rem==='reroute_soonest' ? '' : 'hidden' ?> small hl">Rettigheder (ved omlægning hurtigst muligt): Mad og drikke mens du venter; hotel hvis næste tog først næste dag.</div>
        <div id="assistNoteLaterNow" class="<?= $rem==='reroute_later' ? '' : 'hidden' ?> small hl">Rettigheder (omlægning senere efter ønske): Kun i den oprindelige forsinkelsesperiode, indtil du træffer dit valg.</div>
      </div>

      <?php if (!$assistOff): ?>
      <div id="assistA_now">
        <div class="mt8"><strong>A) Tilbudt assistance</strong></div>
        <?php $mo = (string)($form['meal_offered'] ?? ''); ?>
        <div class="mt4">1. Får du måltider/forfriskninger under ventetiden? (Art. 20(2)(a))</div>
        <label><input type="radio" name="meal_offered" value="yes" <?= $mo==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="meal_offered" value="no" <?= $mo==='no'?'checked':'' ?> /> Nej</label>

        <?php $ho = (string)($form['hotel_offered'] ?? ''); ?>
        <div class="mt8">2. Får du hotel/indkvartering + transport dertil? (Art. 20(2)(b))</div>
        <label><input type="radio" name="hotel_offered" value="yes" <?= $ho==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="hotel_offered" value="no" <?= $ho==='no'?'checked':'' ?> /> Nej</label>
        <?php $on = (string)($form['overnight_needed'] ?? ''); ?>
        <div class="mt4 <?= $ho==='no' ? '' : 'hidden' ?>" id="overnightWrapNow">
          <span>Hvis nej: Bliver overnatning nødvendig?</span>
          <label class="ml8"><input type="radio" name="overnight_needed" value="yes" <?= $on==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="overnight_needed" value="no" <?= $on==='no'?'checked':'' ?> /> Nej</label>
          <div class="small muted mt4">Ved ekstraordinære forhold kan hotel begrænses til 3 nætter.</div>
        </div>

        <?php $bt = (string)($form['blocked_train_alt_transport'] ?? ''); ?>
        <div class="mt8">3. Er toget blokeret på sporet — får du transport væk? (Art. 20(2)(c))</div>
        <label><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <?php else: ?>
        <div class="small mt8 hl">⚠️ Assistance efter Art. 20(2) er markeret som undtaget for denne rejse. Udfyld direkte dine udgifter nedenfor (Art. 18(3)).</div>
      <?php endif; ?>

      <div class="mt12"><strong>B) Alternative transporttjenester</strong></div>
      <?php $ap = (string)($form['alt_transport_provided'] ?? ''); ?>
      <div>4. Får du alternative transporttjenester, hvis forbindelsen er afbrudt? (Art. 20(3))</div>
      <label><input type="radio" name="alt_transport_provided" value="yes" <?= $ap==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="alt_transport_provided" value="no" <?= $ap==='no'?'checked':'' ?> /> Nej</label>

      <fieldset class="fieldset mt12">
        <legend>Dine udgifter (udfyld kun hvis du selv betaler)</legend>
        <div class="small muted">Disse udgifter behandles som refusion af nødvendige udlæg efter Art. 18(3), når vederlagsfri hjælp efter Art. 20 ikke leveres.</div>
        <!-- Måltider egenbetaling -->
        <div class="mt8" <?= $assistOff ? '' : 'data-reveal="meal_offered:no"' ?>>
          <label>Måltider – beløb
            <input type="number" step="0.01" name="meal_self_paid_amount" value="<?= h($form['meal_self_paid_amount'] ?? '') ?>" />
          </label>
          <label class="ml8">Valuta
            <input type="text" name="meal_self_paid_currency" value="<?= h($form['meal_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? '')) ?>" placeholder="EUR" />
          </label>
          <div class="mt4">
            <label class="small">Upload kvittering (PDF/JPG/PNG)
              <input type="file" name="meal_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
            </label>
            <?php $mru = (string)($form['meal_self_paid_receipt'] ?? ''); if ($mru !== ''): ?>
              <div class="small muted mt4">Uploadet: <?= h(basename($mru)) ?></div>
            <?php endif; ?>
          </div>
        </div>
  <!-- Hotel egenbetaling -->
  <div class="mt8" <?= $assistOff ? '' : 'data-reveal="hotel_offered:no"' ?>>
          <label>Hotel – beløb (samlet)
            <input type="number" step="0.01" name="hotel_self_paid_amount" value="<?= h($form['hotel_self_paid_amount'] ?? '') ?>" />
          </label>
          <label class="ml8">Valuta
            <input type="text" name="hotel_self_paid_currency" value="<?= h($form['hotel_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? '')) ?>" placeholder="EUR" />
          </label>
          <label class="ml8">Antal nætter
            <input type="number" step="1" name="hotel_self_paid_nights" value="<?= h($form['hotel_self_paid_nights'] ?? '') ?>" />
          </label>
          <div class="mt4">
            <label class="small">Upload kvittering (PDF/JPG/PNG)
              <input type="file" name="hotel_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
            </label>
            <?php $hru = (string)($form['hotel_self_paid_receipt'] ?? ''); if ($hru !== ''): ?>
              <div class="small muted mt4">Uploadet: <?= h(basename($hru)) ?></div>
            <?php endif; ?>
          </div>
        </div>
  <!-- Evakuering egenbetaling -->
  <div class="mt8" <?= $assistOff ? '' : 'data-reveal="blocked_train_alt_transport:no"' ?>>
          <label>Transport væk – beløb
            <input type="number" step="0.01" name="blocked_self_paid_amount" value="<?= h($form['blocked_self_paid_amount'] ?? '') ?>" />
          </label>
          <label class="ml8">Valuta
            <input type="text" name="blocked_self_paid_currency" value="<?= h($form['blocked_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? '')) ?>" placeholder="EUR" />
          </label>
          <div class="mt4">
            <label class="small">Upload kvittering (PDF/JPG/PNG)
              <input type="file" name="blocked_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
            </label>
            <?php $bru = (string)($form['blocked_self_paid_receipt'] ?? ''); if ($bru !== ''): ?>
              <div class="small muted mt4">Uploadet: <?= h(basename($bru)) ?></div>
            <?php endif; ?>
          </div>
        </div>
  <!-- Alternativ transport egenbetaling -->
  <div class="mt8" data-reveal="alt_transport_provided:no">
          <label>Alternativ transport til destination – beløb
            <input type="number" step="0.01" name="alt_self_paid_amount" value="<?= h($form['alt_self_paid_amount'] ?? '') ?>" />
          </label>
          <label class="ml8">Valuta
            <input type="text" name="alt_self_paid_currency" value="<?= h($form['alt_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? '')) ?>" placeholder="EUR" />
          </label>
          <div class="mt4">
            <label class="small">Upload kvittering (PDF/JPG/PNG)
              <input type="file" name="alt_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
            </label>
            <?php $aru = (string)($form['alt_self_paid_receipt'] ?? ''); if ($aru !== ''): ?>
              <div class="small muted mt4">Uploadet: <?= h(basename($aru)) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="small muted mt4">Tip: Angiv en fælles valuta under "Dokumentation & udgifter", feltet "Valuta". Den bruges som fallback her.</div>
      </fieldset>

      <!-- Removed legacy C) Dokumentation & udgifter block; per-field uploads are provided below each beløbfelt -->

      

      

      <?php if ($assistOff): ?>
        <div class="small mt8 hl">⚠️ Assistance (måltider/hotel/transport) kan være undtaget her. Vi logger stadig udgifter og afprøver krav efter lokal praksis.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</fieldset>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
  <?= $this->Html->link('← Tilbage', ['action' => 'choices'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
  <?= $this->Form->button('Næste →', ['class' => 'button']) ?>
</div>

<?= $this->Form->end() ?>

<script>
// Minimal conditional reveal: show self-paid fields when corresponding assistance was NOT offered
function updateReveal() {
  document.querySelectorAll('[data-reveal]')?.forEach(function(el){
    var spec = el.getAttribute('data-reveal');
    if (!spec) return;
    var parts = spec.split(':');
    if (parts.length !== 2) return;
    var name = parts[0]; var val = parts[1];
    var checked = document.querySelector('input[name="'+name+'"]:checked');
    var on = checked ? (checked.value === val) : false;
    el.style.display = on ? '' : 'none';
  });
}
document.addEventListener('change', function(e){
  if (e && e.target && ['meal_offered','hotel_offered','blocked_train_alt_transport','alt_transport_provided'].indexOf(e.target.name) !== -1) {
    updateReveal();
  }
});
document.addEventListener('DOMContentLoaded', updateReveal);
</script>
