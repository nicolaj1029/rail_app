<?php
$railPostIncident = is_array($railPostIncident ?? null) ? (array)$railPostIncident : [];
if ($railPostIncident === []) {
    return;
}

$mode = strtolower(trim((string)($railPostIncident['mode'] ?? 'completed')));
$completedSelected = array_values(array_filter((array)($railPostIncident['completed_selected'] ?? []), 'is_array'));
$events = array_values(array_filter((array)($railPostIncident['events'] ?? []), 'is_array'));
$liveExpenses = array_values(array_filter((array)($railPostIncident['live_expenses'] ?? []), 'is_array'));
$liveTotals = array_values(array_filter((array)($railPostIncident['live_totals'] ?? []), 'is_array'));
$headline = trim((string)($headline ?? ''));
?>
<style>
  .tc6-rail-post-strip { margin: 0 0 16px; padding: 14px; border: 1px solid var(--tc-border); border-radius: var(--tc-r-lg); background: linear-gradient(180deg, #f7fafc 0%, #ffffff 100%); }
  .tc6-rail-post-strip__head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:12px; }
  .tc6-rail-post-strip__title { font-size:15px; font-weight:700; color:var(--tc-text-1); }
  .tc6-rail-post-strip__copy { font-size:13px; color:var(--tc-text-2); }
  .tc6-rail-post-strip__pills { display:flex; gap:8px; flex-wrap:wrap; }
  .tc6-rail-post-strip__pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:#eef5ff; border:1px solid #cdddff; color:#17407a; font-size:13px; font-weight:600; }
  .tc6-rail-post-strip__grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
  .tc6-rail-post-strip__card { padding:12px; border:1px solid var(--tc-border); border-radius:12px; background:#fff; }
  .tc6-rail-post-strip__meta { display:flex; justify-content:space-between; gap:8px; align-items:flex-start; margin-bottom:8px; }
  .tc6-rail-post-strip__label { font-size:14px; font-weight:700; color:var(--tc-text-1); }
  .tc6-rail-post-strip__location { font-size:12px; color:var(--tc-text-2); }
  .tc6-rail-post-strip__status { display:inline-flex; align-items:center; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:700; }
  .tc6-rail-post-strip__status[data-status="active"] { background:#fff4d6; color:#8a5b00; }
  .tc6-rail-post-strip__status[data-status="resolved"] { background:#e7f8ee; color:#17663a; }
  .tc6-rail-post-strip__resolution { font-size:13px; color:var(--tc-text-2); }
  .tc6-rail-post-strip__expense-list { display:grid; gap:8px; }
  .tc6-rail-post-strip__expense { display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border:1px solid var(--tc-border); border-radius:12px; background:#fff; }
  .tc6-rail-post-strip__expense-label { font-size:13px; font-weight:600; color:var(--tc-text-1); }
  .tc6-rail-post-strip__expense-copy { font-size:12px; color:var(--tc-text-2); }
  .tc6-rail-post-strip__totals { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
  .tc6-rail-post-strip__total { display:inline-flex; padding:6px 10px; border-radius:999px; background:#0f2743; color:#fff; font-size:12px; font-weight:700; }
  .tc6-rail-post-strip__empty { font-size:13px; color:var(--tc-text-2); }
  @media (max-width: 760px) {
    .tc6-rail-post-strip__grid { grid-template-columns:1fr; }
    .tc6-rail-post-strip__expense { flex-direction:column; }
  }
</style>

<section class="tc6-rail-post-strip">
  <div class="tc6-rail-post-strip__head">
    <div>
      <div class="tc6-rail-post-strip__title"><?= h($headline !== '' ? $headline : ($mode === 'ongoing' ? 'Live overblik' : 'Valgte spor efter haendelsen')) ?></div>
      <div class="tc6-rail-post-strip__copy">
        <?= h($mode === 'ongoing'
            ? 'Vi bevarer historikken, mens sagen udvikler sig. Du kan tilfoeje overslagsbeloeb nu og rette senere.'
            : 'Vaelg kun de spor, der faktisk blev relevante efter rail-haendelsen. De aabner de relevante efterfoelgende trin.') ?>
      </div>
    </div>
  </div>

  <?php if ($mode === 'completed'): ?>
    <?php if ($completedSelected === []): ?>
      <div class="tc6-rail-post-strip__empty">Ingen efterfoelgende spor er valgt endnu.</div>
    <?php else: ?>
      <div class="tc6-rail-post-strip__pills">
        <?php foreach ($completedSelected as $item): ?>
          <span class="tc6-rail-post-strip__pill"><?= h((string)($item['label'] ?? '')) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <?php if ($events !== []): ?>
      <div class="tc6-rail-post-strip__grid">
        <?php foreach ($events as $event): ?>
          <article class="tc6-rail-post-strip__card">
            <div class="tc6-rail-post-strip__meta">
              <div>
                <div class="tc6-rail-post-strip__label"><?= h((string)($event['title'] ?? 'Haendelse')) ?></div>
                <?php if (trim((string)($event['location_label'] ?? '')) !== ''): ?>
                  <div class="tc6-rail-post-strip__location"><?= h((string)$event['location_label']) ?></div>
                <?php endif; ?>
              </div>
              <span class="tc6-rail-post-strip__status" data-status="<?= h((string)($event['status'] ?? 'active')) ?>"><?= h((string)($event['status_label'] ?? 'Aktiv')) ?></span>
            </div>
            <?php if (trim((string)($event['resolution'] ?? '')) !== ''): ?>
              <div class="tc6-rail-post-strip__resolution"><?= h((string)$event['resolution']) ?></div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="tc6-rail-post-strip__empty">Ingen live-haendelser er registreret endnu.</div>
    <?php endif; ?>

    <?php if ($liveExpenses !== []): ?>
      <div class="tc6-rail-post-strip__title" style="margin-top:14px;">Live udgifter</div>
      <div class="tc6-rail-post-strip__expense-list">
        <?php foreach ($liveExpenses as $expense): ?>
          <div class="tc6-rail-post-strip__expense">
            <div>
              <div class="tc6-rail-post-strip__expense-label"><?= h((string)($expense['label'] ?? 'Udgift')) ?></div>
              <div class="tc6-rail-post-strip__expense-copy"><?= h((string)($expense['receipt_label'] ?? 'Kvittering mangler')) ?></div>
            </div>
            <div class="tc6-rail-post-strip__expense-label"><?= h((string)($expense['amount_label'] ?? 'Beloeb ikke indtastet endnu')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($liveTotals !== []): ?>
        <div class="tc6-rail-post-strip__totals">
          <?php foreach ($liveTotals as $total): ?>
            <span class="tc6-rail-post-strip__total"><?= h((string)($total['amount_label'] ?? '')) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
