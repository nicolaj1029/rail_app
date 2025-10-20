<?php
/** @var \App\View\AppView $this */
?>
<h2>Flow · Details (TRIN 1–2)</h2>
<?= $this->Form->create(null) ?>
<div>
    <label>Travel state</label>
    <?= $this->Form->select('travel_state', [
        'completed' => 'Rejsen er afsluttet',
        'ongoing' => 'Rejsen er påbegyndt',
        'before_start' => 'Rejsen starter senere',
    ], ['empty' => 'Vælg...']) ?>
</div>
<div>
    <label>EU only?</label>
    <?= $this->Form->checkbox('eu_only') ?>
</div>
<div>
    <label>Delay at final (min)</label>
    <?= $this->Form->number('delay_min_eu', ['min' => 0]) ?>
</div>
<fieldset>
    <legend>Incident</legend>
        <?= $this->Form->radio('incident_main', [
        ['value' => 'delay', 'text' => 'Delay'],
        ['value' => 'cancellation', 'text' => 'Cancellation'],
    ], ['empty' => true]) ?>
        <?php $main = $incident['main'] ?? ''; ?>
        <div id="missedRowDetails" class="mt-2 <?= ($main==='delay'||$main==='cancellation') ? '' : 'hidden' ?>">
            <div>Har det medført en missed connection?</div>
            <label><input type="radio" name="missed_connection" value="yes" <?= !empty($incident['missed'])?'checked':'' ?> /> Ja</label>
            <label style="margin-left:8px;"><input type="radio" name="missed_connection" value="no" <?= empty($incident['missed'])?'checked':'' ?> /> Nej</label>
        </div>
</fieldset>
<script>
    (function(){
        var container = document.getElementById('missedRowDetails');
        function update(){
            var sel = document.querySelector('input[name="incident_main"]:checked');
            if (!container) return;
            if (sel && (sel.value === 'delay' || sel.value === 'cancellation')) { container.classList.remove('hidden'); }
            else { container.classList.add('hidden'); }
        }
        document.querySelectorAll('input[name="incident_main"]').forEach(function(r){ r.addEventListener('change', update); });
        update();
    })();
</script>
<div>
    <?= $this->Form->button('Next →') ?>
</div>
<?= $this->Form->end() ?>
<p>
    Eller brug one-page: <?= $this->Html->link('Alle trin på én side', ['action' => 'one']) ?>
</p>
