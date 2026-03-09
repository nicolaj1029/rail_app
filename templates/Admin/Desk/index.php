<?php
/** @var \App\View\AppView $this */
/** @var string $role */
/** @var array<string,mixed> $inbox */
$items = (array)($inbox['items'] ?? []);
$stats = (array)($inbox['stats'] ?? []);
$currentUrl = $this->Url->build($this->getRequest()->getRequestTarget());
?>
<style>
  .desk-page { max-width: 1280px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .desk-grid { display:grid; grid-template-columns: 1.2fr 2fr; gap:16px; align-items:start; }
  .desk-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .desk-title { margin:0 0 8px; }
  .desk-muted { color:#64748b; }
  .desk-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(120px,1fr)); gap:10px; margin-top:12px; }
  .desk-stat { border:1px solid #e5e7eb; border-radius:10px; padding:12px; background:#f8fafc; }
  .desk-stat strong { display:block; font-size:24px; color:#0f172a; }
  .desk-toolbar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:12px; }
  .desk-button, .desk-chip { display:inline-flex; align-items:center; justify-content:center; gap:6px; border-radius:10px; padding:9px 12px; border:1px solid #cbd5e1; background:#fff; color:#0f172a; text-decoration:none; cursor:pointer; }
  .desk-button.primary { background:#0f172a; color:#fff; border-color:#0f172a; }
  .desk-chip.active { background:#dbeafe; border-color:#93c5fd; }
  .desk-list { display:grid; gap:10px; }
  .desk-item { border:1px solid #e5e7eb; border-radius:12px; padding:14px; background:#fff; display:grid; gap:8px; }
  .desk-item-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
  .desk-item-title { font-weight:700; color:#0f172a; }
  .desk-badge { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; background:#eef2ff; color:#3730a3; }
  .desk-meta { display:flex; gap:12px; flex-wrap:wrap; font-size:13px; color:#475569; }
  .desk-actions { display:flex; gap:8px; flex-wrap:wrap; }
  .desk-note { border-left:4px solid #0a6fd8; background:#f5faff; padding:10px 12px; border-radius:8px; margin-top:12px; }
  @media (max-width: 980px) { .desk-grid { grid-template-columns:1fr; } }
</style>

<div class="desk-page">
  <h1 class="desk-title">Admin Desk</h1>
  <div class="desk-muted">Dette er driftspanelet til live sagsbehandling. Brug det som cockpit sammen med passageren. De gamle admin-sider findes stadig som fallback.</div>

  <div class="desk-grid" style="margin-top:16px;">
    <section class="desk-card">
      <h2 class="desk-title">Arbejdstilstand</h2>
      <div class="desk-muted">Jurist ser juridisk vurdering og overrides. Operator ser kun sikre handlinger og eskalering.</div>
      <form method="post" action="<?= h($this->Url->build('/admin/desk/role')) ?>" class="desk-toolbar">
        <input type="hidden" name="_csrfToken" value="<?= h((string)$this->getRequest()->getAttribute('csrfToken')) ?>">
        <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
        <button class="desk-chip <?= $role === 'jurist' ? 'active' : '' ?>" type="submit" name="role" value="jurist">Jurist</button>
        <button class="desk-chip <?= $role === 'operator' ? 'active' : '' ?>" type="submit" name="role" value="operator">Operator</button>
      </form>

      <div class="desk-stats">
        <div class="desk-stat"><div class="desk-muted">Alle</div><strong><?= (int)($stats['all'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Afventer passager</div><strong><?= (int)($stats['awaiting_passenger'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Under behandling</div><strong><?= (int)($stats['in_review'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Juridisk review</div><strong><?= (int)($stats['legal_review'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Klar til indsendelse</div><strong><?= (int)($stats['ready_to_submit'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Indsendt</div><strong><?= (int)($stats['submitted'] ?? 0) ?></strong></div>
      </div>

      <div class="desk-note">
        <strong><?= $role === 'jurist' ? 'Jurist-mode' : 'Operator-mode' ?></strong><br>
        <?= $role === 'jurist'
          ? 'Brug cockpit til artikelvurdering, policy-check og endelig beslutning. Operator-playbooks og eskalering er stadig synlige.'
          : 'Hold dig til intake, dokumenter, chat og status. Brug “Send til jurist” når policy eller jura er uklar.' ?>
      </div>

      <div class="desk-toolbar">
        <a class="desk-button primary" href="<?= h($this->Url->build('/admin/desk/view?source=session&id=current')) ?>">Åbn live cockpit</a>
        <a class="desk-button" href="<?= h($this->Url->build('/admin/chat')) ?>">Admin chat</a>
        <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/create-from-session')) ?>">Opret sag fra session</a>
        <a class="desk-button" href="<?= h($this->Url->build('/admin/audit/latest')) ?>">Audit</a>
      </div>
    </section>

    <section class="desk-card">
      <h2 class="desk-title">Inbox</h2>
      <div class="desk-muted">Samler live session, sager, claims og shadow-filer i én arbejdsliste.</div>
      <div class="desk-list" style="margin-top:12px;">
        <?php foreach ($items as $item): ?>
          <article class="desk-item">
            <div class="desk-item-head">
              <div>
                <div class="desk-item-title"><?= h((string)($item['title'] ?? '')) ?></div>
                <div class="desk-muted"><?= h((string)($item['subtitle'] ?? '')) ?></div>
              </div>
              <span class="desk-badge"><?= h((string)($item['ops_status_label'] ?? '')) ?></span>
            </div>
            <div class="desk-meta">
              <span>Kilde: <?= h((string)($item['source'] ?? '')) ?></span>
              <?php if (($item['delay_minutes'] ?? null) !== null): ?><span>Forsinkelse: <?= h((string)$item['delay_minutes']) ?> min</span><?php endif; ?>
              <?php if (trim((string)($item['ticket_mode'] ?? '')) !== ''): ?><span>Billet: <?= h((string)$item['ticket_mode']) ?></span><?php endif; ?>
              <span>Opdateret: <?= h((string)($item['updated_at'] ?? '')) ?></span>
            </div>
            <div class="desk-muted"><?= h((string)($item['next_action'] ?? '')) ?></div>
            <div class="desk-actions">
              <a class="desk-button primary" href="<?= h($this->Url->build('/admin/desk/view?source=' . urlencode((string)$item['source']) . '&id=' . urlencode((string)$item['id']))) ?>">Åbn cockpit</a>
              <?php if (($item['source'] ?? '') === 'session'): ?>
                <a class="desk-button" href="<?= h($this->Url->build('/admin/chat')) ?>">Live chat</a>
              <?php elseif (($item['source'] ?? '') === 'case'): ?>
                <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/view/' . $item['id'])) ?>">Gammel case-visning</a>
              <?php elseif (($item['source'] ?? '') === 'claim'): ?>
                <a class="desk-button" href="<?= h($this->Url->build('/admin/claims/view/' . $item['id'])) ?>">Claim-detalje</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</div>
