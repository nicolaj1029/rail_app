<?php
/** @var \App\View\AppView $this */
/** @var string $role */
/** @var string $source */
/** @var string $id */
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
$playbooks = $playbooks ?? [];
$redirectUrl = $this->Url->build('/admin/desk/view?source=' . urlencode($source) . '&id=' . urlencode($id));
?>
<style>
  .desk-page { max-width: 1360px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .desk-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; }
  .desk-grid { display:grid; grid-template-columns: 1.3fr .9fr; gap:16px; align-items:start; margin-top:16px; }
  .desk-stack { display:grid; gap:16px; }
  .desk-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .desk-title { margin:0 0 8px; }
  .desk-muted { color:#64748b; }
  .desk-kv { display:grid; grid-template-columns: 150px 1fr; gap:8px; font-size:14px; }
  .desk-kv dt { font-weight:700; color:#0f172a; }
  .desk-kv dd { margin:0; color:#334155; }
  .desk-badge { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; background:#eef2ff; color:#3730a3; }
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
  @media (max-width: 980px) { .desk-grid { grid-template-columns:1fr; } }
</style>

<div class="desk-page">
  <?php if ($cockpit === null): ?>
    <div class="desk-card">
      <h1 class="desk-title">Cockpit ikke fundet</h1>
      <div class="desk-muted">Kilden eller id'et findes ikke længere.</div>
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

        <section class="desk-card">
          <h2 class="desk-title"><?= $role === 'jurist' ? 'Juridisk vurdering' : 'Juridisk snapshot' ?></h2>
          <dl class="desk-kv">
            <?php foreach ($legalPanel as $label => $value): ?>
              <dt><?= h((string)$label) ?></dt>
              <dd><?= is_bool($value) ? ($value ? 'ja' : 'nej') : h($value === null || $value === '' ? '-' : (string)$value) ?></dd>
            <?php endforeach; ?>
          </dl>
          <?php if ($role === 'operator'): ?>
            <div class="desk-note">
              <strong>Operator-regel</strong><br>
              Brug dette panel som orientering. Hvis policy eller artikelvurdering er uklar, skift status til <em>Juridisk review</em>.
            </div>
          <?php endif; ?>
        </section>

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
