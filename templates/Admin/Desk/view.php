<?php
/** @var \App\View\AppView $this */
/** @var string $role */
/** @var string $source */
/** @var string $id */
/** @var string $ref */
/** @var array<string,mixed>|null $cockpit */
/** @var array<string,string> $allowedStatuses */
/** @var list<array<string,mixed>> $playbooks */
/** @var bool $roleLocked */
/** @var string $authUser */
/** @var string $roleLabel */
$csrfToken = (string)($this->getRequest()->getAttribute('csrfToken') ?? '');
$item = (array)($cockpit['item'] ?? []);
$summaryRows = (array)($cockpit['summary_rows'] ?? []);
$actionPanel = (array)($cockpit['action_panel'] ?? []);
$opsPanel = (array)($cockpit['ops_panel'] ?? []);
$legalPanel = (array)($cockpit['legal_panel'] ?? []);
$citations = (array)($cockpit['citations'] ?? []);
$history = (array)($cockpit['history'] ?? []);
$attachments = (array)($cockpit['attachments'] ?? []);
$steps = (array)($opsPanel['steps'] ?? []);
$blockers = (array)($opsPanel['blockers'] ?? []);
$actions = (array)($opsPanel['actions'] ?? []);
$notes = (array)($cockpit['notes'] ?? []);
$followUp = (array)($cockpit['follow_up'] ?? []);
$riskPanel = (array)($cockpit['risk_panel'] ?? []);
$riskFlags = (array)($riskPanel['flags'] ?? []);
$ticketReview = (array)($cockpit['ticket_review'] ?? []);
$opsReview = (array)($cockpit['ops_review'] ?? []);
$opsChecks = (array)($opsReview['match_checks'] ?? []);
$playbooks = $playbooks ?? [];
$redirectUrl = $this->Url->build('/admin/desk/view?source=' . urlencode($source) . '&id=' . urlencode($id));
$legalLabels = [
    'liable_party' => 'Ansvarlig part',
    'compensation_eligible' => 'Kompensation mulig',
    'compensation_amount' => 'Kompensationsbeloeb',
    'compensation_amount_eur' => 'Kompensationsbeloeb',
    'compensation_pct' => 'Kompensationsniveau',
    'refund_eligible' => 'Afhjaelpning mulig',
    'refund_choice' => 'Valgt afhjælpningsspor',
    'remedy_choice' => 'Valgt afhjælpningsspor',
    'care_eligible' => 'Assistance aktiv',
    'care_status' => 'Assistancestatus',
    'art20_expenses_total' => 'Assistanceudgifter i alt',
    'eu_only' => 'EU-only',
    'extraordinary' => 'Extraordinary review',
    'currency' => 'Valuta',
    'distance_km' => 'Distance',
    'distance_band' => 'Distancekategori',
    'operator' => 'Operator',
    'incident_type' => 'Haendelse',
];
$formatDeskValue = static function ($value): string {
    if (is_bool($value)) {
        return $value ? 'Ja' : 'Nej';
    }
    if (is_array($value)) {
        return $value === [] ? '-' : implode(', ', array_map(static fn($item): string => (string)$item, $value));
    }
    if ($value === null) {
        return '-';
    }
    $text = trim((string)$value);
    return $text !== '' ? $text : '-';
};
$formatDeskLabel = static function (string $key) use ($legalLabels): string {
    if (isset($legalLabels[$key])) {
        return $legalLabels[$key];
    }
    return ucfirst(str_replace('_', ' ', $key));
};
?>
<style>
  html, body { overflow-x:hidden; }
  .desk-page { max-width: 1480px; margin: 0 auto; padding: 16px 20px 32px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; overflow-x:hidden; }
  .desk-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; }
  .desk-grid { display:grid; grid-template-columns:minmax(0, 1.75fr) minmax(320px, 0.95fr); gap:16px; align-items:start; margin-top:16px; }
  .desk-stack { display:grid; gap:16px; min-width:0; }
  .desk-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,.04); min-width:0; overflow:hidden; }
  .desk-title { margin:0 0 8px; }
  .desk-muted { color:#64748b; }
  .desk-kv { display:grid; grid-template-columns:minmax(120px, 180px) minmax(0, 1fr); gap:8px 12px; font-size:14px; align-items:start; }
  .desk-kv dt { font-weight:700; color:#0f172a; }
  .desk-kv dd { margin:0; color:#334155; overflow-wrap:anywhere; word-break:break-word; }
  .desk-badge { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; background:#eef2ff; color:#3730a3; border:1px solid transparent; }
  .desk-risk-low { background:#ecfccb; color:#3f6212; border-color:#bef264; }
  .desk-risk-medium { background:#fef3c7; color:#92400e; border-color:#fcd34d; }
  .desk-risk-high { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
  .desk-toolbar { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
  .desk-button { display:inline-flex; align-items:center; justify-content:center; gap:6px; border-radius:10px; padding:9px 12px; border:1px solid #cbd5e1; background:#fff; color:#0f172a; text-decoration:none; cursor:pointer; }
  .desk-button.primary { background:#0f172a; color:#fff; border-color:#0f172a; }
  .desk-panel-list { display:grid; gap:10px; }
  .desk-panel-item { border:1px solid #e5e7eb; border-radius:10px; padding:12px; background:#fafafa; }
  .desk-panel-item.warning { border-color:#fde68a; background:#fffbeb; }
  .desk-panel-item strong { display:block; margin-bottom:4px; color:#0f172a; }
  .desk-history { max-height:360px; overflow:auto; display:grid; gap:10px; }
  .desk-msg { border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; background:#fff; }
  .desk-msg small { display:block; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
  .desk-steps { display:flex; gap:8px; flex-wrap:wrap; }
  .desk-step { padding:6px 10px; border-radius:999px; border:1px solid #e2e8f0; background:#f8fafc; font-size:13px; }
  .desk-note { border-left:4px solid #0a6fd8; background:#f5faff; padding:10px 12px; border-radius:8px; margin-top:12px; }
  pre.desk-pre { max-height:320px; overflow:auto; font-size:12px; background:#0f172a; color:#e2e8f0; padding:12px; border-radius:10px; }
  .desk-side-card { position:sticky; top:16px; }
  .desk-legal { display:grid; gap:12px; }
  .desk-legal-row { display:grid; grid-template-columns:minmax(130px, 180px) minmax(0, 1fr); gap:10px 12px; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#fafafa; }
  .desk-legal-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .desk-legal-value { color:#0f172a; font-weight:600; overflow-wrap:anywhere; word-break:break-word; }
  .desk-panel-item,
  .desk-toolbar,
  .desk-note,
  .desk-step { min-width:0; }
  @media (max-width: 980px) { .desk-grid { grid-template-columns:1fr; } }
  @media (max-width: 980px) { .desk-side-card { position:static; } }
  @media (max-width: 720px) {
    .desk-page { padding:12px; }
    .desk-kv, .desk-legal-row { grid-template-columns:1fr; }
  }
</style>

<div class="desk-page">
  <?php if ($cockpit === null): ?>
    <div class="desk-card">
      <h1 class="desk-title">Cockpit ikke fundet</h1>
      <div class="desk-muted">Kilden eller id'et findes ikke længere.</div>
      <div class="desk-muted" style="margin-top:8px;">Source: <?= h($source) ?> · id: <?= h($id) ?><?php if (!empty($ref)): ?> · ref: <?= h($ref) ?><?php endif; ?></div>
      <div class="desk-toolbar">
        <a class="desk-button primary" href="<?= h($this->Url->build('/admin/desk')) ?>">Tilbage til inbox</a>
      </div>
    </div>
  <?php else: ?>
    <div class="desk-head">
      <div>
        <h1 class="desk-title"><?= h((string)($item['title'] ?? 'Case cockpit')) ?></h1>
        <div class="desk-muted"><?= h((string)($item['subtitle'] ?? '')) ?></div>
        <div class="desk-toolbar">
          <span class="desk-badge"><?= h((string)($item['ops_status_label'] ?? '')) ?></span>
          <?php if (!empty($riskPanel['evaluated'])): ?>
            <span class="desk-badge <?= h((string)($riskPanel['badge_class'] ?? 'desk-risk-low')) ?>"><?= h((string)($riskPanel['level_label'] ?? 'Low risk')) ?></span>
          <?php endif; ?>
          <?php if (!empty($riskPanel['fraud_review_required'])): ?>
            <span class="desk-badge desk-risk-high">Fraud review</span>
          <?php endif; ?>
          <?php if (!empty($ticketReview['available'])): ?>
            <span class="desk-badge <?= h((string)($ticketReview['badge_class'] ?? 'desk-risk-medium')) ?>"><?= h((string)($ticketReview['label'] ?? 'Billet review')) ?></span>
          <?php endif; ?>
          <?php if (!empty($opsReview['available'])): ?>
            <span class="desk-badge <?= h((string)($opsReview['badge_class'] ?? 'desk-risk-medium')) ?>"><?= h((string)($opsReview['label'] ?? 'Ops data')) ?></span>
          <?php endif; ?>
          <span class="desk-badge"><?= h($role === 'jurist' ? 'Jurist' : 'Operator') ?></span>
          <?php if ($roleLocked): ?>
            <span class="desk-badge"><?= h($roleLabel) ?> · <?= h($authUser !== '' ? $authUser : 'ukendt') ?></span>
          <?php endif; ?>
          <span class="desk-badge"><?= h((string)$source) ?></span>
        </div>
      </div>
      <div class="desk-toolbar">
        <a class="desk-button" href="<?= h($this->Url->build('/admin/desk')) ?>">Tilbage til inbox</a>
        <a class="desk-button" href="<?= h($this->Url->build('/admin/chat')) ?>">Admin chat</a>
        <?php if ($source === 'session'): ?>
          <a class="desk-button primary" href="<?= h($this->Url->build('/admin/cases/create-from-session')) ?>">Opret sag fra live session</a>
          <?php if (trim((string)(($item['meta']['ref'] ?? ''))) !== ''): ?>
            <a class="desk-button" href="<?= $this->Url->build(['prefix' => false, 'controller' => 'Passenger', 'action' => 'case', '?' => ['ref' => (string)$item['meta']['ref'], 'admin' => '1']]) ?>">Aabn klientsag</a>
          <?php endif; ?>
          <?php if (strtolower(trim((string)(($item['meta']['transport_mode'] ?? '')))) === 'air'): ?>
            <a class="desk-button" href="<?= h($this->Url->build('/reimbursement/official?template=Form_air_travel/air_travel_form.pdf')) ?>" target="_blank" rel="noopener">air_travel_form.pdf</a>
            <a class="desk-button" href="<?= h($this->Url->build('/reimbursement/official?template=Staevning_template_air_DK/staevning-flysag-uncompressed.pdf')) ?>" target="_blank" rel="noopener">staevning-flysag.pdf</a>
          <?php endif; ?>
        <?php elseif ($source === 'case'): ?>
          <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/passenger/' . $id)) ?>">Aabn klientsag</a>
          <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/edit/' . $id)) ?>">Rediger admin-sag</a>
          <?php if (strtolower(trim((string)(($item['meta']['transport_mode'] ?? '')))) === 'air'): ?>
            <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/air-travel-form/' . $id)) ?>" target="_blank" rel="noopener">air_travel_form.pdf</a>
            <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/air-statement-form/' . $id)) ?>" target="_blank" rel="noopener">staevning-flysag.pdf</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="desk-grid">
      <div class="desk-stack">
        <section class="desk-card">
          <h2 class="desk-title">Snapshot</h2>
          <dl class="desk-kv">
            <?php foreach ($summaryRows as $label => $value): ?>
              <dt><?= h((string)$label) ?></dt>
              <dd><?= h((string)$value !== '' ? (string)$value : '-') ?></dd>
            <?php endforeach; ?>
          </dl>
        </section>

        <section class="desk-card">
          <h2 class="desk-title">Næste handling</h2>
          <div class="desk-panel-list">
            <div class="desk-panel-item">
              <strong>Primært næste skridt</strong>
              <div><?= h((string)($actionPanel['primary'] ?? 'Åbn relevant værktøj')) ?></div>
            </div>
            <?php if (trim((string)($actionPanel['question'] ?? '')) !== ''): ?>
              <div class="desk-panel-item warning">
                <strong>Live spørgsmål</strong>
                <div><?= h((string)$actionPanel['question']) ?></div>
              </div>
            <?php endif; ?>
            <?php $uploadHint = (array)($actionPanel['upload_hint'] ?? []); ?>
            <?php if ($uploadHint !== []): ?>
              <div class="desk-panel-item">
                <strong><?= h((string)($uploadHint['title'] ?? 'Upload')) ?></strong>
                <div><?= h((string)($uploadHint['text'] ?? '')) ?></div>
              </div>
            <?php endif; ?>
          </div>
          <div class="desk-toolbar">
            <?php if ($source === 'session'): ?>
              <a class="desk-button primary" href="<?= h($this->Url->build('/admin/chat')) ?>">Betjen passageren live</a>
              <a class="desk-button" href="<?= h($this->Url->build('/flow/start')) ?>" target="_blank">Åbn flow</a>
            <?php elseif ($source === 'case'): ?>
              <a class="desk-button primary" href="<?= h($this->Url->build('/admin/cases/view/' . $id)) ?>">Åbn case</a>
              <a class="desk-button" href="<?= h($this->Url->build('/admin/cases/edit/' . $id)) ?>">Redigér</a>
            <?php elseif ($source === 'claim'): ?>
              <a class="desk-button primary" href="<?= h($this->Url->build('/admin/claims/view/' . $id)) ?>">Åbn claim</a>
            <?php endif; ?>
          </div>
        </section>

        <section class="desk-card">
          <h2 class="desk-title">Drift og blockers</h2>
          <div class="desk-panel-list">
            <?php foreach ($actions as $panelAction): ?>
              <div class="desk-panel-item">
                <strong><?= h((string)($panelAction['title'] ?? 'Handling')) ?></strong>
                <div><?= h((string)($panelAction['text'] ?? '')) ?></div>
              </div>
            <?php endforeach; ?>
            <?php foreach ($blockers as $blocker): ?>
              <div class="desk-panel-item warning">
                <strong><?= h((string)($blocker['title'] ?? $blocker['label'] ?? 'Blocker')) ?></strong>
                <div><?= h((string)($blocker['text'] ?? $blocker['description'] ?? '')) ?></div>
              </div>
            <?php endforeach; ?>
            <?php if ($actions === [] && $blockers === []): ?>
              <div class="desk-panel-item">
                <strong>Ingen akutte blockers</strong>
                <div>Panelet har ingen kendte blockers for denne sag lige nu.</div>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($source !== 'shadow_case'): ?>
            <form method="post" action="<?= h($this->Url->build('/admin/desk/update-status')) ?>" class="desk-toolbar">
              <input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>">
              <input type="hidden" name="source" value="<?= h($source) ?>">
              <input type="hidden" name="id" value="<?= h($id) ?>">
              <input type="hidden" name="redirect" value="<?= h($redirectUrl) ?>">
              <select name="status">
                <?php foreach ($allowedStatuses as $statusValue => $label): ?>
                  <option value="<?= h($statusValue) ?>" <?= (($item['ops_status'] ?? '') === $statusValue) ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="desk-button primary" type="submit">Gem driftstatus</button>
              <?php if ($role === 'operator'): ?>
                <button class="desk-button" type="submit" name="status" value="legal_review">Send til jurist</button>
              <?php endif; ?>
            </form>
          <?php endif; ?>
        </section>

        <section class="desk-card">
          <h2 class="desk-title">Interne noter</h2>
          <form method="post" action="<?= h($this->Url->build('/admin/desk/note')) ?>" class="desk-panel-list">
            <input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="source" value="<?= h($source) ?>">
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <input type="hidden" name="redirect" value="<?= h($redirectUrl) ?>">
            <textarea name="note" rows="3" style="width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:10px;" placeholder="Skriv intern note eller kort samtalereferat ..."></textarea>
            <div class="desk-toolbar">
              <button class="desk-button primary" type="submit">Gem note</button>
            </div>
          </form>
          <div class="desk-panel-list" style="margin-top:12px;">
            <?php if ($notes === []): ?>
              <div class="desk-panel-item">
                <strong>Ingen noter</strong>
                <div>Der er endnu ikke gemt interne noter på denne sag.</div>
              </div>
            <?php endif; ?>
            <?php foreach ($notes as $note): ?>
              <div class="desk-panel-item">
                <strong><?= h((string)($note['author'] ?? 'admin')) ?> · <?= h((string)($note['role'] ?? '')) ?></strong>
                <div class="desk-muted"><?= h((string)($note['created_at'] ?? '')) ?></div>
                <div><?= h((string)($note['text'] ?? '')) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="desk-card">
          <h2 class="desk-title">Opfølgning</h2>
          <form method="post" action="<?= h($this->Url->build('/admin/desk/follow-up')) ?>" class="desk-panel-list">
            <input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="source" value="<?= h($source) ?>">
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <input type="hidden" name="redirect" value="<?= h($redirectUrl) ?>">
            <input type="datetime-local" name="follow_up_at" value="<?= h(trim((string)($followUp['due_at'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string)$followUp['due_at'])) : '') ?>" style="border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px;">
            <input type="text" name="follow_up_reason" value="<?= h((string)($followUp['reason'] ?? '')) ?>" placeholder="Hvorfor skal der følges op?" style="border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px;">
            <div class="desk-toolbar">
              <button class="desk-button primary" type="submit">Gem opfølgning</button>
            </div>
          </form>
          <?php if (trim((string)($followUp['due_at'] ?? '')) !== ''): ?>
            <div class="desk-note">
              <strong>Næste opfølgning</strong><br>
              <?= h(date('d-m-Y H:i', strtotime((string)$followUp['due_at']))) ?>
              <?php if (trim((string)($followUp['reason'] ?? '')) !== ''): ?>
                · <?= h((string)$followUp['reason']) ?>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="desk-muted">Ingen opfølgning planlagt endnu.</div>
          <?php endif; ?>
        </section>

        <section class="desk-card">
          <h2 class="desk-title"><?= $role === 'jurist' ? 'Jurist-playbooks' : 'Operator-playbooks' ?></h2>
          <div class="desk-panel-list">
            <?php foreach ($playbooks as $playbook): ?>
              <div class="desk-panel-item">
                <strong><?= h((string)($playbook['title'] ?? 'Playbook')) ?></strong>
                <div><?= h((string)($playbook['text'] ?? '')) ?></div>
              </div>
            <?php endforeach; ?>
            <?php if ($playbooks === []): ?>
              <div class="desk-panel-item">
                <strong>Ingen playbooks</strong>
                <div>Denne sag har ingen særlige driftsinstruktioner endnu.</div>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($history !== []): ?>
          <section class="desk-card">
            <h2 class="desk-title">Live chat historik</h2>
            <div class="desk-history">
              <?php foreach ($history as $msg): ?>
                <div class="desk-msg">
                  <small><?= h((string)($msg['role'] ?? '')) ?></small>
                  <div><?= h((string)($msg['content'] ?? '')) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if (!empty($cockpit['snapshot'])): ?>
          <section class="desk-card">
            <h2 class="desk-title">Snapshot JSON</h2>
            <pre class="desk-pre"><?= h((string)json_encode($cockpit['snapshot'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
          </section>
        <?php endif; ?>
      </div>

      <div class="desk-stack">
        <section class="desk-card">
          <h2 class="desk-title">Visible steps</h2>
          <?php if ($steps !== []): ?>
            <div class="desk-steps">
              <?php foreach ($steps as $step): ?>
                <span class="desk-step">
                  Trin <?= h((string)($step['ui_num'] ?? '')) ?> · <?= h((string)($step['title'] ?? '')) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="desk-muted">Ingen trin registreret for denne sag.</div>
          <?php endif; ?>
        </section>

        <section class="desk-card desk-side-card">
          <h2 class="desk-title"><?= $role === 'jurist' ? 'Juridisk vurdering' : 'Juridisk snapshot' ?></h2>
          <div class="desk-legal">
            <?php foreach ($legalPanel as $label => $value): ?>
              <div class="desk-legal-row">
                <div class="desk-legal-label"><?= h($formatDeskLabel((string)$label)) ?></div>
                <div class="desk-legal-value"><?= h($formatDeskValue($value)) ?></div>
              </div>
            <?php endforeach; ?>
            <?php if ($legalPanel === []): ?>
              <div class="desk-panel-item">
                <strong>Ingen juridiske nøgler</strong>
                <div>Panelet har ikke modtaget et juridisk snapshot endnu.</div>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($role === 'operator'): ?>
            <div class="desk-note">
              <strong>Operator-regel</strong><br>
              Brug dette panel som orientering. Hvis policy eller artikelvurdering er uklar, skift status til <em>Juridisk review</em>.
            </div>
          <?php endif; ?>
        </section>

        <?php if (!empty($ticketReview['available'])): ?>
          <section class="desk-card">
            <h2 class="desk-title">Billetverificering</h2>
            <div class="desk-toolbar">
              <span class="desk-badge <?= h((string)($ticketReview['badge_class'] ?? 'desk-risk-medium')) ?>"><?= h((string)($ticketReview['label'] ?? 'Billet review')) ?></span>
            </div>
            <div class="desk-muted" style="margin-top:12px;"><?= h((string)($ticketReview['summary'] ?? '')) ?></div>
          </section>
        <?php endif; ?>

        <?php if (!empty($opsReview['available'])): ?>
          <section class="desk-card">
            <h2 class="desk-title"><?= h((string)($opsReview['title'] ?? 'Operationelle transportdata')) ?></h2>
            <div class="desk-toolbar">
              <span class="desk-badge <?= h((string)($opsReview['badge_class'] ?? 'desk-risk-medium')) ?>"><?= h((string)($opsReview['label'] ?? 'Ops data')) ?></span>
              <?php if (trim((string)($opsReview['source_label'] ?? ($opsReview['source'] ?? ''))) !== ''): ?>
                <span class="desk-badge"><?= h((string)($opsReview['source_label'] ?? strtoupper((string)$opsReview['source']))) ?></span>
              <?php endif; ?>
              <?php if (trim((string)($opsReview['confidence'] ?? '')) !== ''): ?>
                <span class="desk-badge">Confidence <?= h((string)$opsReview['confidence']) ?></span>
              <?php endif; ?>
              <span class="desk-badge">Score <?= (int)($opsReview['evidence_score'] ?? 0) ?></span>
            </div>

            <div class="desk-muted" style="margin-top:12px;"><?= h((string)($opsReview['summary'] ?? '')) ?></div>

            <dl class="desk-kv" style="margin-top:12px;">
              <dt><?= h((string)($opsReview['status_label'] ?? 'Status')) ?></dt>
              <dd><?= h((string)($opsReview['status'] ?? '') !== '' ? (string)$opsReview['status'] : '-') ?></dd>
              <dt><?= h((string)($opsReview['cancelled_label'] ?? 'Aflyst')) ?></dt>
              <dd><?= ((string)($opsReview['cancelled'] ?? 'no') === 'yes') ? 'ja' : 'nej' ?></dd>
              <dt><?= h((string)($opsReview['delay_label'] ?? 'Est. ankomstafvigelse')) ?></dt>
              <dd>
                <?php $opsDelay = $opsReview['delay_minutes_estimated'] ?? null; ?>
                <?= ($opsDelay === null || $opsDelay === '') ? '-' : h((string)$opsDelay . ' min') ?>
              </dd>
              <dt><?= h((string)($opsReview['planned_label'] ?? 'Planlagt')) ?></dt>
              <dd>
                <?= h((string)($opsReview['scheduled_departure_local'] ?? '') !== '' ? (string)$opsReview['scheduled_departure_local'] : '-') ?>
                <?php if (trim((string)($opsReview['scheduled_arrival_local'] ?? '')) !== ''): ?>
                  → <?= h((string)$opsReview['scheduled_arrival_local']) ?>
                <?php endif; ?>
              </dd>
              <dt><?= h((string)($opsReview['observed_label'] ?? 'Observeret')) ?></dt>
              <dd>
                <?php
                  $observedDeparture = trim((string)($opsReview['actual_departure_local'] ?? '')) !== ''
                    ? (string)$opsReview['actual_departure_local']
                    : (string)($opsReview['estimated_departure_local'] ?? '');
                  $observedArrival = trim((string)($opsReview['actual_arrival_local'] ?? '')) !== ''
                    ? (string)$opsReview['actual_arrival_local']
                    : (string)($opsReview['estimated_arrival_local'] ?? '');
                ?>
                <?= h($observedDeparture !== '' ? $observedDeparture : '-') ?>
                <?php if ($observedArrival !== ''): ?>
                  → <?= h($observedArrival) ?>
                <?php endif; ?>
              </dd>
            </dl>

            <div class="desk-panel-list" style="margin-top:12px;">
              <?php foreach ($opsChecks as $check): ?>
                <div class="desk-panel-item <?= ((string)($check['status'] ?? '') === 'mismatch') ? 'warning' : '' ?>">
                  <strong><?= h((string)($check['label'] ?? 'Match check')) ?></strong>
                  <div class="desk-muted">
                    Frontflow: <?= h((string)($check['current'] ?? '') !== '' ? (string)$check['current'] : '-') ?>
                    · Detekteret: <?= h((string)($check['detected'] ?? '') !== '' ? (string)$check['detected'] : '-') ?>
                  </div>
                  <div><?= h(ucfirst((string)($check['status'] ?? 'unknown'))) ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="desk-note">
              <strong><?= h((string)($opsReview['support_note_title'] ?? 'Ops-regel')) ?></strong><br>
              <?= h((string)($opsReview['support_note'] ?? 'Operationelle transportdata bruges her som drifts- og plausibilitetsstoette. Dataene er ikke alene juridisk facit.')) ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if (!empty($riskPanel['evaluated'])): ?>
          <section class="desk-card">
            <h2 class="desk-title">Risk review</h2>
            <div class="desk-toolbar">
              <span class="desk-badge <?= h((string)($riskPanel['badge_class'] ?? 'desk-risk-low')) ?>"><?= h((string)($riskPanel['level_label'] ?? 'Low risk')) ?></span>
              <span class="desk-badge">Score <?= (int)($riskPanel['score'] ?? 0) ?></span>
              <?php if (!empty($riskPanel['fraud_review_required'])): ?>
                <span class="desk-badge desk-risk-high">Fraud review required</span>
              <?php endif; ?>
            </div>
            <div class="desk-muted" style="margin-top:12px;"><?= h((string)($riskPanel['summary'] ?? '')) ?></div>
            <div class="desk-panel-list" style="margin-top:12px;">
              <?php if ($riskFlags === []): ?>
                <div class="desk-panel-item">
                  <strong>Ingen flag</strong>
                  <div>Fase 1 fandt ingen konkrete risikosignaler.</div>
                </div>
              <?php endif; ?>
              <?php foreach ($riskFlags as $flag): ?>
                <div class="desk-panel-item <?= (($flag['severity'] ?? '') === 'high') ? 'warning' : '' ?>">
                  <strong><?= h((string)($flag['label'] ?? 'Risk flag')) ?></strong>
                  <div><?= h((string)($flag['detail'] ?? '')) ?></div>
                  <div class="desk-muted">
                    Kode: <?= h((string)($flag['code'] ?? '')) ?>
                    <?php if (($flag['points'] ?? 0) > 0): ?>
                      · <?= (int)$flag['points'] ?> point
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($attachments !== []): ?>
          <section class="desk-card">
            <h2 class="desk-title">Bilag</h2>
            <div class="desk-panel-list">
              <?php foreach ($attachments as $attachment): ?>
                <div class="desk-panel-item">
                  <strong><?= h((string)($attachment['name'] ?? 'Bilag')) ?></strong>
                  <div><?= h((string)($attachment['type'] ?? '')) ?></div>
                  <?php if (trim((string)($attachment['path'] ?? '')) !== ''): ?>
                    <div class="desk-toolbar">
                      <a class="desk-button" href="/<?= h((string)$attachment['path']) ?>" target="_blank">Åbn bilag</a>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($citations !== []): ?>
          <section class="desk-card">
            <h2 class="desk-title">Citations</h2>
            <div class="desk-panel-list">
              <?php foreach ($citations as $citation): ?>
                <div class="desk-panel-item">
                  <strong><?= h((string)($citation['label'] ?? 'Citation')) ?></strong>
                  <div><?= h((string)($citation['quote'] ?? '')) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
