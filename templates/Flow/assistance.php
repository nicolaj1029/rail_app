<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$profile = $profile ?? ['articles' => []];
$isCompleted = (!empty($flags['travel_state']) && $flags['travel_state'] === 'completed');
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

      <div class="mt12"><strong>B) Alternative transporttjenester</strong></div>
      <?php $ap = (string)($form['alt_transport_provided'] ?? ''); ?>
      <div>4. Fik du alternative transporttjenester, hvis forbindelsen blev afbrudt? (Art. 20(3))</div>
      <label><input type="radio" name="alt_transport_provided" value="yes" <?= $ap==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="alt_transport_provided" value="no" <?= $ap==='no'?'checked':'' ?> /> Nej</label>

      <div class="mt12"><strong>C) Dokumentation & udgifter</strong></div>
      <?php $exu = (string)($form['extra_expense_upload'] ?? ''); ?>
      <div>5. Har du haft udgifter (taxi, bus, hotel, mad osv.)? (Upload kvitteringer)</div>
      <input type="file" name="extra_expense_upload" />
      <?php if ($exu !== ''): ?><div class="small muted mt4">Uploadet: <?= h(basename($exu)) ?></div><?php endif; ?>
      <div class="grid-3 mt8">
        <label>Måltider (beløb)
          <input type="number" step="0.01" name="expense_breakdown_meals" value="<?= h($form['expense_breakdown_meals'] ?? '') ?>" />
        </label>
        <label>Hotel (nætter)
          <input type="number" step="1" name="expense_breakdown_hotel_nights" value="<?= h($form['expense_breakdown_hotel_nights'] ?? '') ?>" />
        </label>
        <label>Lokal transport (beløb)
          <input type="number" step="0.01" name="expense_breakdown_local_transport" value="<?= h($form['expense_breakdown_local_transport'] ?? '') ?>" />
        </label>
        <label>Andre beløb
          <input type="number" step="0.01" name="expense_breakdown_other_amounts" value="<?= h($form['expense_breakdown_other_amounts'] ?? '') ?>" />
        </label>
        <label>Valuta
          <input type="text" name="expense_breakdown_currency" value="<?= h($form['expense_breakdown_currency'] ?? '') ?>" placeholder="EUR" />
        </label>
      </div>

      <?php $dcr = (string)($form['delay_confirmation_received'] ?? ''); ?>
      <div class="mt8">6. Fik du skriftlig bekræftelse på forsinkelse/aflysning/mistet forbindelse? (Art. 20(4))</div>
      <label><input type="radio" name="delay_confirmation_received" value="yes" <?= $dcr==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="delay_confirmation_received" value="no" <?= $dcr==='no'?'checked':'' ?> /> Nej</label>
      <?php $dcu = (string)($form['delay_confirmation_upload'] ?? ''); ?>
      <div class="mt4">
        <input type="file" name="delay_confirmation_upload" />
        <?php if ($dcu !== ''): ?><div class="small muted mt4">Uploadet: <?= h(basename($dcu)) ?></div><?php endif; ?>
      </div>

      

      <?php if (isset($profile['articles']['art20_2']) && $profile['articles']['art20_2'] === false): ?>
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

      <div class="mt12"><strong>B) Alternative transporttjenester</strong></div>
      <?php $ap = (string)($form['alt_transport_provided'] ?? ''); ?>
      <div>4. Får du alternative transporttjenester, hvis forbindelsen er afbrudt? (Art. 20(3))</div>
      <label><input type="radio" name="alt_transport_provided" value="yes" <?= $ap==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="alt_transport_provided" value="no" <?= $ap==='no'?'checked':'' ?> /> Nej</label>

      <div class="mt12"><strong>C) Dokumentation & udgifter</strong></div>
      <?php $exu = (string)($form['extra_expense_upload'] ?? ''); ?>
      <div>5. Har du udgifter (taxi, bus, hotel, mad osv.)? (Upload kvitteringer)</div>
      <input type="file" name="extra_expense_upload" />
      <?php if ($exu !== ''): ?><div class="small muted mt4">Uploadet: <?= h(basename($exu)) ?></div><?php endif; ?>
      <div class="grid-3 mt8">
        <label>Måltider (beløb)
          <input type="number" step="0.01" name="expense_breakdown_meals" value="<?= h($form['expense_breakdown_meals'] ?? '') ?>" />
        </label>
        <label>Hotel (nætter)
          <input type="number" step="1" name="expense_breakdown_hotel_nights" value="<?= h($form['expense_breakdown_hotel_nights'] ?? '') ?>" />
        </label>
        <label>Lokal transport (beløb)
          <input type="number" step="0.01" name="expense_breakdown_local_transport" value="<?= h($form['expense_breakdown_local_transport'] ?? '') ?>" />
        </label>
        <label>Andre beløb
          <input type="number" step="0.01" name="expense_breakdown_other_amounts" value="<?= h($form['expense_breakdown_other_amounts'] ?? '') ?>" />
        </label>
        <label>Valuta
          <input type="text" name="expense_breakdown_currency" value="<?= h($form['expense_breakdown_currency'] ?? '') ?>" placeholder="EUR" />
        </label>
      </div>

      <?php $dcr = (string)($form['delay_confirmation_received'] ?? ''); ?>
      <div class="mt8">6. Får du skriftlig bekræftelse på forsinkelse/aflysning/mistet forbindelse? (Art. 20(4))</div>
      <label><input type="radio" name="delay_confirmation_received" value="yes" <?= $dcr==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="delay_confirmation_received" value="no" <?= $dcr==='no'?'checked':'' ?> /> Nej</label>
      <?php $dcu = (string)($form['delay_confirmation_upload'] ?? ''); ?>
      <div class="mt4">
        <input type="file" name="delay_confirmation_upload" />
        <?php if ($dcu !== ''): ?><div class="small muted mt4">Uploadet: <?= h(basename($dcu)) ?></div><?php endif; ?>
      </div>

      

      <?php if (isset($profile['articles']['art20_2']) && $profile['articles']['art20_2'] === false): ?>
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
