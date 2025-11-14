<?php /** @var \App\View\AppView $this */ ?>
<h1>Flow Admin Panel</h1>
<p class="small">Dette panel styrer admin-mode for den segmenterede flow-formular (/flow/start osv.). Admin-mode muliggør visning og ændring af EU-only scope i TRIN 1 og hooks-panelet.</p>
<div style="margin:12px 0;">
  <form method="post" action="<?= $this->Url->build(['action' => 'toggle']) ?>">
    <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
      <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
    <?php endif; ?>
    <button type="submit" class="button">Skift admin-mode (nu: <?= $isAdminMode ? 'ON' : 'OFF' ?>)</button>
  </form>
</div>
<?php if ($isAdminMode): ?>
  <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>Admin-mode aktiv</strong>
    <p class="small">Gå til <a href="<?= $this->Url->build('/flow/start') ?>">TRIN 1 start</a> for at bruge EU-only checkbox. EU-only ændringer gemmes i session under flow.compute.euOnly.</p>
  </div>
<?php else: ?>
  <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>Admin-mode inaktiv</strong>
    <p class="small">Passager-flowet skjuler EU-only kontroller. Aktivér admin-mode for at få adgang.</p>
  </div>
<?php endif; ?>

<h2 style="margin-top:24px;">Flow session snapshot</h2>
<pre style="background:#f6f8fa; padding:12px; border-radius:6px; max-height:400px; overflow:auto; font-size:12px;">
<?= h(json_encode($flow, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?>
</pre>
