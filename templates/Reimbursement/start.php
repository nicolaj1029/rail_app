<?php
/** @var \App\View\AppView $this */
?>
<div class="content">
  <h1>Reimbursement form (demo)</h1>
  <p>Udfyld felterne nedenfor for at generere en enkel PDF-opsummering. Vi kan senere udfylde den officielle PDF direkte.</p>
  <div style="margin:8px 0; display:flex; gap:8px; align-items:center;">
    <?php
      $files = glob(CONFIG . 'demo' . DIRECTORY_SEPARATOR . '*.json') ?: [];
      $fixtures = [];
      foreach ($files as $f) {
        $base = basename($f, '.json');
        // Human label from filename
        $label = ucwords(str_replace(['_', '-'], [' ', ' '], $base));
        $fixtures[$base] = $label;
      }
      if (empty($fixtures)) {
        $fixtures = ['ice_125m' => 'Ice 125m'];
      }
    ?>
    <label for="demo-case" style="margin:0;">Vælg eksempel:</label>
    <select id="demo-case">
      <?php foreach ($fixtures as $value => $label): ?>
        <option value="<?= h($value) ?>" <?= $value === 'ice_125m' ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" id="load-demo" class="button">Indlæs eksempel</button>
  </div>
  <?= $this->Form->create(null, ['url' => ['action' => 'generate']]) ?>
    <fieldset>
      <legend>Kontakt</legend>
      <?= $this->Form->control('name', ['label' => 'Navn']) ?>
      <?= $this->Form->control('email', ['label' => 'Email']) ?>
    </fieldset>
    <fieldset>
      <legend>Your journey details</legend>
      <?= $this->Form->control('operator', ['label' => 'Railway undertaking']) ?>
      <?= $this->Form->control('dep_date', ['label' => 'Departure date (dd/mm/yyyy)']) ?>
      <?= $this->Form->control('dep_station', ['label' => 'Departure station']) ?>
      <?= $this->Form->control('arr_station', ['label' => 'Destination station']) ?>
      <?= $this->Form->control('dep_time', ['label' => 'Scheduled departure (hh:mm)']) ?>
      <?= $this->Form->control('arr_time', ['label' => 'Scheduled arrival (hh:mm)']) ?>
      <?= $this->Form->control('train_no', ['label' => 'Train no./category']) ?>
      <?= $this->Form->control('ticket_no', ['label' => 'Ticket No(s)/Booking Ref']) ?>
      <?= $this->Form->control('price', ['label' => 'Ticket price(s)']) ?>
      <?= $this->Form->control('actual_arrival_date', ['label' => 'Date of actual arrival (dd/mm/yyyy)']) ?>
      <?= $this->Form->control('actual_dep_time', ['label' => 'Actual departure time (hh:mm)']) ?>
      <?= $this->Form->control('actual_arr_time', ['label' => 'Actual arrival time (hh:mm)']) ?>
      <?= $this->Form->control('missed_connection_station', ['label' => 'Missed connection in (station)']) ?>
    </fieldset>
    <fieldset>
      <legend>Reason for claim</legend>
      <?= $this->Form->control('reason_delay', ['label' => 'Delay', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('reason_cancellation', ['label' => 'Cancellation', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('reason_missed_conn', ['label' => 'Missed connection', 'type' => 'checkbox']) ?>
    </fieldset>
    <?= $this->Form->button('Generér PDF (opsummering)') ?>
  <?= $this->Form->end() ?>

  <?= $this->Form->create(null, ['url' => ['action' => 'official']]) ?>
    <?php /* Reuse same set of fields for official PDF; minimal subset repeated */ ?>
    <?= $this->Form->hidden('name', ['id' => 'off_name']) ?>
    <?= $this->Form->hidden('email', ['id' => 'off_email']) ?>
    <?= $this->Form->hidden('operator', ['id' => 'off_operator']) ?>
    <?= $this->Form->hidden('dep_date', ['id' => 'off_dep_date']) ?>
    <?= $this->Form->hidden('dep_station', ['id' => 'off_dep_station']) ?>
    <?= $this->Form->hidden('arr_station', ['id' => 'off_arr_station']) ?>
    <?= $this->Form->hidden('dep_time', ['id' => 'off_dep_time']) ?>
    <?= $this->Form->hidden('arr_time', ['id' => 'off_arr_time']) ?>
    <?= $this->Form->hidden('train_no', ['id' => 'off_train_no']) ?>
    <?= $this->Form->hidden('ticket_no', ['id' => 'off_ticket_no']) ?>
    <?= $this->Form->hidden('price', ['id' => 'off_price']) ?>
    <?= $this->Form->hidden('actual_arrival_date', ['id' => 'off_actual_arrival_date']) ?>
    <?= $this->Form->hidden('actual_dep_time', ['id' => 'off_actual_dep_time']) ?>
    <?= $this->Form->hidden('actual_arr_time', ['id' => 'off_actual_arr_time']) ?>
    <?= $this->Form->hidden('missed_connection_station', ['id' => 'off_missed_connection_station']) ?>
    <?= $this->Form->hidden('reason_delay', ['id' => 'off_reason_delay']) ?>
    <?= $this->Form->hidden('reason_cancellation', ['id' => 'off_reason_cancellation']) ?>
    <?= $this->Form->hidden('reason_missed_conn', ['id' => 'off_reason_missed_conn']) ?>
    <?= $this->Form->button('Udfyld officiel formular (FPDI)') ?>
  <?= $this->Form->end() ?>

    <script>
    (function(){
      // Keep hidden official fields in sync with visible inputs
      const forms = document.querySelectorAll('form');
      const dataForm = forms[0];
      function syncFieldByName(name){
        const src = dataForm ? dataForm.querySelector(`[name="${name}"]`) : null;
        const off = document.getElementById('off_' + name);
        if (!src || !off) return;
        if (src.type === 'checkbox') {
          off.value = src.checked ? '1' : '';
        } else {
          off.value = src.value || '';
        }
      }
      function syncAll(){
        if (!dataForm) return;
        const els = dataForm.querySelectorAll('input[name], select[name], textarea[name]');
        els.forEach(el => {
          if (!el.name) return;
          syncFieldByName(el.name);
        });
      }
      if (dataForm) {
        dataForm.addEventListener('input', function(e){
          const el = e.target;
          if (el && el.name) syncFieldByName(el.name);
        });
        dataForm.addEventListener('change', function(e){
          const el = e.target;
          if (el && el.name) syncFieldByName(el.name);
        });
        // Initial sync in case of prefilled values
        syncAll();
      }
      const btn = document.getElementById('load-demo');
      const sel = document.getElementById('demo-case');
      if (!btn) return;
      const demoUrl = <?= json_encode($this->Url->build('/api/demo/fixtures')) ?>; // respects base path (/rail_app)
      btn.addEventListener('click', async function(){
        try {
          btn.disabled = true; btn.textContent = 'Indlæser…';
          const caseName = sel ? sel.value : 'ice_125m';
          const res = await fetch(demoUrl + '?case=' + encodeURIComponent(caseName));
          if (!res.ok) {
            const text = await res.text();
            throw new Error('HTTP ' + res.status + ' ' + res.statusText + ' — ' + text.slice(0, 200));
          }
          let data;
          try { data = await res.json(); } catch(e) {
            const text = await res.text();
            throw new Error('Ugyldigt JSON-svar: ' + text.slice(0, 200));
          }
          const j = data.journey || {};
          const map = {
            name: 'John Doe',
            email: 'john@example.com',
            operator: j.operatorName?.value,
            dep_date: j.depDate?.value,
            dep_station: j.depStation?.value,
            arr_station: j.arrStation?.value,
            dep_time: j.schedDepTime?.value,
            arr_time: j.schedArrTime?.value,
            train_no: [j.trainNo?.value, j.trainCategory?.value].filter(Boolean).join(' '),
            ticket_no: j.bookingRef?.value || j.ticketNumber?.value,
            price: j.ticketPrice?.value,
            actual_arrival_date: j.actualArrDate?.value,
            actual_dep_time: j.actualDepTime?.value,
            actual_arr_time: j.actualArrTime?.value,
            missed_connection_station: j.missedConnectionAt?.value
          };
          for (const [k,v] of Object.entries(map)){
            const el = document.querySelector(`[name="${k}"]`);
            if (el && v) el.value = v;
            const off = document.getElementById('off_' + k);
            if (off && v) off.value = v;
          }
          // Sync reason checkboxes into official hidden inputs
          const reasons = ['reason_delay','reason_cancellation','reason_missed_conn'];
          for (const r of reasons) {
            const cb = document.querySelector(`[name="${r}"]`);
            const off = document.getElementById('off_' + r);
            if (cb && off) off.value = cb.checked ? '1' : '';
          }
          // Simple defaults per case (optional)
          if (sel && sel.value === 'ter_missed_conn') {
            const cb = document.querySelector('[name="reason_missed_conn"]');
            if (cb) cb.checked = true;
            const off = document.getElementById('off_reason_missed_conn');
            if (off) off.value = '1';
          }
          btn.textContent = 'Eksempel indlæst ✔';
          // Re-sync to ensure hidden fields reflect any programmatic changes
          syncAll();
        } catch(e) { console.error(e); }
        finally { btn.disabled = false; }
      });
    })();
    </script>

  <p>Se projektmateriale: <?= $this->Html->link('Flow charts', ['controller' => 'Project', 'action' => 'index']) ?></p>
</div>
