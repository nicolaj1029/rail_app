<?php
/** @var \App\View\AppView $this */
/** @var string $role */
/** @var array<string,mixed> $inbox */
/** @var bool $roleLocked */
/** @var string $authUser */
/** @var string $roleLabel */
/** @var string $filter */
/** @var string $search */
/** @var array<string,mixed> $railTransport */
$items = (array)($inbox['items'] ?? []);
$stats = (array)($inbox['stats'] ?? []);
$availableFilters = (array)($inbox['available_filters'] ?? []);
$currentUrl = $this->Url->build($this->getRequest()->getRequestTarget());
$railTransport = (array)($railTransport ?? []);
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
  .desk-badge { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; background:#eef2ff; color:#3730a3; border:1px solid transparent; }
  .desk-risk-low { background:#ecfccb; color:#3f6212; border-color:#bef264; }
  .desk-risk-medium { background:#fef3c7; color:#92400e; border-color:#fcd34d; }
  .desk-risk-high { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
  .desk-meta { display:flex; gap:12px; flex-wrap:wrap; font-size:13px; color:#475569; }
  .desk-actions { display:flex; gap:8px; flex-wrap:wrap; }
  .desk-note { border-left:4px solid #0a6fd8; background:#f5faff; padding:10px 12px; border-radius:8px; margin-top:12px; }
  code.desk-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  .desk-filter-form { display:grid; gap:10px; margin-top:12px; }
  .desk-filter-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .desk-input, .desk-select { border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px; background:#fff; color:#0f172a; min-width:180px; }
  @media (max-width: 980px) { .desk-grid { grid-template-columns:1fr; } }
</style>

<div class="desk-page">
  <h1 class="desk-title">Admin Desk</h1>
  <div class="desk-muted">Dette er driftspanelet til live sagsbehandling. Brug det som cockpit sammen med passageren. De gamle admin-sider findes stadig som fallback.</div>

  <div class="desk-grid" style="margin-top:16px;">
    <section class="desk-card">
      <h2 class="desk-title">Arbejdstilstand</h2>
      <div class="desk-muted">Jurist ser juridisk vurdering og overrides. Operator ser kun sikre handlinger og eskalering.</div>
      <?php if ($roleLocked): ?>
        <div class="desk-note">
          <strong>Logget ind som <?= h($roleLabel) ?></strong><br>
          Brugeren <code class="desk-code"><?= h($authUser !== '' ? $authUser : 'ukendt') ?></code> er bundet til rollen <strong><?= h($role === 'jurist' ? 'Jurist' : 'Operator') ?></strong>.
        </div>
      <?php else: ?>
        <form method="post" action="<?= h($this->Url->build('/admin/desk/role')) ?>" class="desk-toolbar">
          <input type="hidden" name="_csrfToken" value="<?= h((string)$this->getRequest()->getAttribute('csrfToken')) ?>">
          <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
          <button class="desk-chip <?= $role === 'jurist' ? 'active' : '' ?>" type="submit" name="role" value="jurist">Jurist</button>
          <button class="desk-chip <?= $role === 'operator' ? 'active' : '' ?>" type="submit" name="role" value="operator">Operator</button>
        </form>
      <?php endif; ?>

      <div class="desk-stats">
        <div class="desk-stat"><div class="desk-muted">Alle</div><strong><?= (int)($stats['all'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Afventer passager</div><strong><?= (int)($stats['awaiting_passenger'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Under behandling</div><strong><?= (int)($stats['in_review'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Juridisk review</div><strong><?= (int)($stats['legal_review'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Klar til indsendelse</div><strong><?= (int)($stats['ready_to_submit'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Indsendt</div><strong><?= (int)($stats['submitted'] ?? 0) ?></strong></div>
        <div class="desk-stat"><div class="desk-muted">Fraud review</div><strong><?= (int)($stats['fraud_review'] ?? 0) ?></strong></div>
      </div>

      <div class="desk-note">
        <strong><?= $role === 'jurist' ? 'Jurist-mode' : 'Operator-mode' ?></strong><br>
        <?= $role === 'jurist'
          ? 'Brug cockpit til artikelvurdering, policy-check og endelig beslutning. Operator-playbooks og eskalering er stadig synlige.'
          : 'Hold dig til intake, dokumenter, chat og status. Brug "Send til jurist" når policy eller jura er uklar.' ?>
      </div>

      <div class="desk-toolbar">
        <a class="desk-button primary" href="<?= h($this->Url->build('/admin/desk/view?source=session&id=current')) ?>">Åbn live cockpit</a>
        <a class="desk-button" href="<?= h($this->Url->build('/admin/chat')) ?>">Admin chat</a>
        <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/create-from-session')) ?>">Opret sag fra session</a>
        <a class="desk-button" href="<?= h($this->Url->build('/admin/audit/latest')) ?>">Audit</a>
      </div>
      <div class="desk-note">
        <strong>Air regressions</strong><br>
        Brug verified air-fixtures direkte herfra, hvis du vil QA'e ongoing/completed uden at starte et nyt frontend-flow.
        <div class="desk-toolbar" style="margin-top:10px;">
          <a class="desk-button" href="<?= h($this->Url->build('/api/demo/v2/scenarios?transport=air&withEval=1&compact=1')) ?>" target="_blank" rel="noopener">Alle air-scenarier</a>
          <a class="desk-button" href="<?= h($this->Url->build('/api/demo/v2/run-scenarios?transport=air')) ?>" target="_blank" rel="noopener">Run all air</a>
          <a class="desk-button" href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=air_delay_ongoing_five_plus&withEval=1&compact=1')) ?>" target="_blank" rel="noopener">Ongoing delay 5+</a>
          <a class="desk-button" href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=air_cancellation_eu_departure&withEval=1&compact=1')) ?>" target="_blank" rel="noopener">Completed cancellation</a>
        </div>
      </div>
      <div class="desk-note">
        <strong>Ferry regressions</strong><br>
        Brug verified ferry-fixtures direkte herfra, hvis du vil QA'e Art. 17/18/19 og PMR uden at starte et nyt frontend-flow.
        <div class="desk-toolbar" style="margin-top:10px;">
          <a class="desk-button" href="<?= h($this->Url->build('/api/demo/v2/scenarios?transport=ferry&withEval=1&compact=1')) ?>" target="_blank" rel="noopener">Alle ferry-scenarier</a>
          <a class="desk-button" href="<?= h($this->Url->build('/api/demo/v2/run-scenarios?transport=ferry')) ?>" target="_blank" rel="noopener">Run all ferry</a>
          <a class="desk-button" href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=ferry_direct_delay_90_terminal&withEval=1&compact=1')) ?>" target="_blank" rel="noopener">Delay 90+ terminal</a>
          <a class="desk-button" href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=ferry_weather_safety_hotel_exclusion&withEval=1&compact=1')) ?>" target="_blank" rel="noopener">Weather + hotel carveout</a>
        </div>
      </div>
      <div class="desk-note">
        <strong>Rail transport service</strong><br>
        <?= h((string)($railTransport['message'] ?? 'Ingen status')) ?><br>
        <?php if (!empty($railTransport['configured'])): ?>
          Base URL: <code class="desk-code"><?= h((string)($railTransport['base_url'] ?? '')) ?></code><br>
        <?php endif; ?>
        <?php if (!empty($railTransport['provider_order'])): ?>
          Provider-rækkefølge: <?= h(implode(' → ', array_map('strval', (array)$railTransport['provider_order']))) ?><br>
        <?php endif; ?>
        <div class="desk-toolbar" style="margin-top:10px;">
          <span class="desk-badge <?= !empty($railTransport['ok']) ? 'desk-risk-low' : 'desk-risk-high' ?>">
            <?= !empty($railTransport['ok']) ? 'Service up' : 'Service down/off' ?>
          </span>
          <?php if (!empty($railTransport['configured'])): ?>
            <a class="desk-button" href="<?= h((string)($railTransport['base_url'] ?? '') . '/health') ?>" target="_blank" rel="noopener">/health</a>
            <a class="desk-button" href="<?= h((string)($railTransport['base_url'] ?? '')) ?>" target="_blank" rel="noopener">/</a>
          <?php endif; ?>
        </div>
        <form method="post" action="<?= h($this->Url->build('/admin/desk/' . (!empty(($railTransport['manager']['running'] ?? false)) ? 'stop-rail-transport' : 'start-rail-transport'))) ?>" class="desk-toolbar">
          <input type="hidden" name="_csrfToken" value="<?= h((string)$this->getRequest()->getAttribute('csrfToken')) ?>">
          <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
          <?php if (!empty(($railTransport['manager']['running'] ?? false))): ?>
            <button class="desk-button" type="submit">Stop rail service</button>
          <?php else: ?>
            <button class="desk-button primary" type="submit">Start rail service</button>
          <?php endif; ?>
        </form>
        <div class="desk-muted" style="margin-top:10px;">
          Dev-start:
          <code class="desk-code">cd services/rail-transport-service</code>
          <code class="desk-code">node src/server.mjs</code>
        </div>
        <?php if (!empty($railTransport['manager']['pid'])): ?>
          <div class="desk-muted">PID: <?= h((string)$railTransport['manager']['pid']) ?></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="desk-card">
      <h2 class="desk-title">Inbox</h2>
      <div class="desk-muted">Samler live session, sager, claims og shadow-filer i én arbejdsliste.</div>
      <form method="get" action="<?= h($this->Url->build('/admin/desk')) ?>" class="desk-filter-form">
        <div class="desk-filter-row">
          <select class="desk-select" name="filter">
            <?php foreach ($availableFilters as $filterValue => $label): ?>
              <option value="<?= h((string)$filterValue) ?>" <?= $filter === (string)$filterValue ? 'selected' : '' ?>><?= h((string)$label) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="desk-input" type="search" name="q" value="<?= h($search) ?>" placeholder="Søg på rute, passager, kilde ...">
          <button class="desk-button primary" type="submit">Filtrér</button>
          <?php if ($filter !== 'all' || trim($search) !== ''): ?>
            <a class="desk-button" href="<?= h($this->Url->build('/admin/desk')) ?>">Nulstil</a>
          <?php endif; ?>
        </div>
      </form>
      <div class="desk-list" style="margin-top:12px;">
        <?php if ($items === []): ?>
          <div class="desk-note">Ingen sager matcher det valgte filter.</div>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
          <?php $risk = (array)($item['risk'] ?? []); ?>
          <?php $ticketReview = (array)($item['ticket_review'] ?? []); ?>
          <?php $opsReview = (array)($item['ops_review'] ?? []); ?>
          <article class="desk-item">
            <div class="desk-item-head">
              <div>
                <div class="desk-item-title"><?= h((string)($item['title'] ?? '')) ?></div>
                <div class="desk-muted"><?= h((string)($item['subtitle'] ?? '')) ?></div>
              </div>
              <div class="desk-actions">
                <span class="desk-badge"><?= h((string)($item['ops_status_label'] ?? '')) ?></span>
                <?php if (!empty($risk['evaluated'])): ?>
                  <span class="desk-badge <?= h((string)($risk['badge_class'] ?? 'desk-risk-low')) ?>"><?= h((string)($risk['level_label'] ?? 'Low risk')) ?></span>
                <?php endif; ?>
                <?php if (!empty($risk['fraud_review_required'])): ?>
                  <span class="desk-badge desk-risk-high">Fraud review</span>
                <?php endif; ?>
                <?php if (!empty($ticketReview['available'])): ?>
                  <span class="desk-badge <?= h((string)($ticketReview['badge_class'] ?? '')) ?>"><?= h((string)($ticketReview['label'] ?? 'Billet review')) ?></span>
                <?php endif; ?>
                <?php if (!empty($opsReview['available'])): ?>
                  <span class="desk-badge <?= h((string)($opsReview['badge_class'] ?? '')) ?>"><?= h((string)($opsReview['label'] ?? 'Ops data')) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="desk-meta">
              <span>Kilde: <?= h((string)($item['source'] ?? '')) ?></span>
              <?php if (($item['delay_minutes'] ?? null) !== null): ?><span>Forsinkelse: <?= h((string)$item['delay_minutes']) ?> min</span><?php endif; ?>
              <?php if (trim((string)($item['ticket_mode'] ?? '')) !== ''): ?><span>Billet: <?= h((string)$item['ticket_mode']) ?></span><?php endif; ?>
              <?php if (trim((string)(($item['follow_up']['due_at'] ?? ''))) !== ''): ?><span>Opfølgning: <?= h(date('d-m-Y H:i', strtotime((string)$item['follow_up']['due_at']))) ?></span><?php endif; ?>
              <?php if (($item['notes_count'] ?? 0) > 0): ?><span>Noter: <?= (int)$item['notes_count'] ?></span><?php endif; ?>
              <span>Opdateret: <?= h((string)($item['updated_at'] ?? '')) ?></span>
            </div>
            <div class="desk-muted"><?= h((string)($item['next_action'] ?? '')) ?></div>
            <?php if (!empty($risk['evaluated'])): ?>
              <div class="desk-muted"><?= h((string)($risk['summary'] ?? '')) ?></div>
            <?php endif; ?>
            <?php if (trim((string)($ticketReview['summary'] ?? '')) !== ''): ?>
              <div class="desk-muted"><?= h((string)($ticketReview['summary'] ?? '')) ?></div>
            <?php endif; ?>
            <?php if (trim((string)($opsReview['summary'] ?? '')) !== ''): ?>
              <div class="desk-muted"><?= h((string)($opsReview['summary'] ?? '')) ?></div>
            <?php endif; ?>
            <?php if (trim((string)(($item['follow_up']['reason'] ?? ''))) !== ''): ?>
              <div class="desk-muted">Opfølgningsårsag: <?= h((string)$item['follow_up']['reason']) ?></div>
            <?php endif; ?>
            <div class="desk-actions">
              <?php
                $cockpitQuery = [
                  'source' => (string)($item['source'] ?? ''),
                  'id' => (string)($item['id'] ?? ''),
                ];
                if (($item['source'] ?? '') === 'case' && trim((string)(($item['meta']['ref'] ?? ''))) !== '') {
                    $cockpitQuery['ref'] = (string)$item['meta']['ref'];
                }
              ?>
              <a class="desk-button primary" href="<?= $this->Url->build(['prefix' => 'Admin', 'controller' => 'Desk', 'action' => 'view', '?' => $cockpitQuery]) ?>">Åbn cockpit</a>
              <?php if (($item['source'] ?? '') === 'session'): ?>
                <a class="desk-button" href="<?= h($this->Url->build('/admin/chat')) ?>">Live chat</a>
                <?php if (trim((string)(($item['meta']['ref'] ?? ''))) !== ''): ?>
                  <a class="desk-button" href="<?= $this->Url->build(['prefix' => false, 'controller' => 'Passenger', 'action' => 'case', '?' => ['ref' => (string)$item['meta']['ref'], 'admin' => '1']]) ?>">Klientsag</a>
                <?php endif; ?>
                <?php if (strtolower(trim((string)(($item['meta']['transport_mode'] ?? '')))) === 'air'): ?>
                  <a class="desk-button" href="<?= h($this->Url->build('/reimbursement/official?template=Form_air_travel/air_travel_form.pdf')) ?>" target="_blank" rel="noopener">air_travel_form.pdf</a>
                  <a class="desk-button" href="<?= h($this->Url->build('/reimbursement/official?template=Staevning_template_air_DK/staevning-flysag-uncompressed.pdf')) ?>" target="_blank" rel="noopener">staevning-flysag.pdf</a>
                <?php endif; ?>
              <?php elseif (($item['source'] ?? '') === 'case'): ?>
                <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/view/' . $item['id'])) ?>">Gammel case-visning</a>
                <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/passenger/' . $item['id'])) ?>">Klientsag</a>
                <?php if (strtolower(trim((string)(($item['meta']['transport_mode'] ?? '')))) === 'air'): ?>
                  <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/air-travel-form/' . $item['id'])) ?>" target="_blank" rel="noopener">air_travel_form.pdf</a>
                  <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/air-statement-form/' . $item['id'])) ?>" target="_blank" rel="noopener">staevning-flysag.pdf</a>
                <?php endif; ?>
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
