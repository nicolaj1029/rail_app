<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $chatPayload */

$payloadJson = (string)json_encode($chatPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$csrfToken = (string)($this->getRequest()->getAttribute('csrfToken') ?? '');
?>
<style>
  .admin-chat-page { display:grid; grid-template-columns: minmax(0, 1.7fr) minmax(320px, 1fr); gap:16px; align-items:start; }
  .admin-chat-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .admin-chat-log { height:560px; overflow:auto; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:12px; }
  .admin-chat-msg { margin-bottom:12px; padding:10px 12px; border-radius:10px; max-width:90%; white-space:pre-wrap; }
  .admin-chat-msg.user { margin-left:auto; background:#0a6fd8; color:#fff; }
  .admin-chat-msg.assistant { background:#fff; border:1px solid #dbe3ea; color:#111; }
  .admin-chat-meta { font-size:12px; color:#666; margin-bottom:4px; text-transform:uppercase; letter-spacing:.04em; }
  .admin-chat-form { display:flex; gap:10px; margin-top:12px; }
  .admin-chat-form input { flex:1; }
  .admin-chat-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
  .admin-chip { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; border:1px solid #d0d7de; background:#fff; color:#111; text-decoration:none; cursor:pointer; }
  .admin-chip:hover { background:#f3f4f6; }
  .admin-list { display:grid; gap:10px; }
  .admin-kv { display:grid; grid-template-columns: 140px 1fr; gap:8px; font-size:13px; }
  .admin-kv dt { font-weight:600; color:#111; }
  .admin-kv dd { margin:0; color:#333; }
  .admin-muted { color:#666; font-size:13px; }
  .admin-section-title { margin:0 0 10px; font-size:16px; color:#111; }
  .admin-question { margin-top:12px; padding:12px; border-left:4px solid #0a6fd8; background:#f5faff; border-radius:8px; }
  .admin-upload-hint { margin-top:12px; padding:12px; border-radius:8px; border:1px solid #bfdbfe; background:#eff6ff; }
  .admin-upload-hint.done { border-color:#bbf7d0; background:#f0fdf4; }
  .admin-steps { margin:0; padding-left:18px; font-size:13px; }
  .admin-citation { border-top:1px solid #eee; padding-top:10px; margin-top:10px; }
  .admin-citation:first-child { margin-top:0; padding-top:0; border-top:0; }
  .admin-toolbar { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
  .admin-action-list { display:grid; gap:8px; margin-top:10px; }
  .admin-action-item { border:1px solid #e5e7eb; border-radius:8px; padding:10px; background:#fafafa; }
  .admin-action-item strong { display:block; color:#111; margin-bottom:4px; }
  .admin-blocker-list { display:grid; gap:8px; margin-top:10px; }
  .admin-blocker-item { border:1px solid #fde68a; border-radius:8px; padding:10px; background:#fffbeb; }
  .admin-blocker-item strong { display:block; color:#111; margin-bottom:4px; }
  .admin-blocker-group { margin-top:12px; }
  .admin-blocker-group:first-child { margin-top:10px; }
  .admin-blocker-group-title { font-size:13px; font-weight:700; color:#111; margin:0 0 8px; text-transform:uppercase; letter-spacing:.03em; }
  .admin-explanation-status { display:inline-flex; align-items:center; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:600; background:#eef2ff; color:#3730a3; margin-bottom:10px; }
  .admin-explanation-status.disabled { background:#f3f4f6; color:#4b5563; }
  .admin-explanation-status.error { background:#fef2f2; color:#991b1b; }
  .admin-explanation-status.cached { background:#ecfeff; color:#155e75; }
  .admin-explanation-body { white-space:pre-wrap; color:#111; line-height:1.5; }
  @media (max-width: 980px) {
    .admin-chat-page { grid-template-columns: 1fr; }
    .admin-chat-log { height:420px; }
  }
</style>

<div class="admin-chat-page"
     data-chat-root
     data-message-url="<?= h($this->Url->build(['action' => 'message'])) ?>"
     data-reset-url="<?= h($this->Url->build(['action' => 'reset'])) ?>"
     data-focus-url="<?= h($this->Url->build(['action' => 'focus'])) ?>"
     data-upload-url="<?= h($this->Url->build(['action' => 'upload'])) ?>"
     data-csrf-token="<?= h($csrfToken) ?>"
     data-initial='<?= h($payloadJson) ?>'>
  <section class="admin-chat-card">
    <h1 style="margin-top:0;">Admin Chat</h1>
    <div class="admin-muted">Deterministisk admin-chat oven paa den eksisterende flow-session. Chatten stiller kun whitelisted spoergsmaal og bruger flow-state som sandhedskilde. Du kan uploade billet eller season-dokument direkte her; chatten bruger den eksisterende extraction-stack og skriver kun whitelistede felter tilbage til flowet.</div>

    <div class="admin-chat-log" data-chat-log></div>

    <div class="admin-question">
      <div class="admin-section-title">Naeste spoergsmaal</div>
      <div data-chat-question class="admin-muted">Ingen aktivt spoergsmaal.</div>
      <div class="admin-chat-actions" data-chat-choices></div>
    </div>

    <div class="admin-upload-hint" data-chat-upload-hint style="display:none;"></div>

    <form class="admin-chat-form" data-chat-form>
      <input type="text" name="message" placeholder="Skriv svar eller brug quick replies" autocomplete="off">
      <button type="submit">Send</button>
    </form>

    <form class="admin-chat-form" data-chat-upload-form enctype="multipart/form-data">
      <input type="file" name="ticket_upload" accept=".pdf,.png,.jpg,.jpeg,.webp,.bmp,.tif,.tiff,.heic,.txt,.text,application/pdf,image/*">
      <button type="submit">Upload</button>
    </form>

    <div class="admin-toolbar">
      <button type="button" class="button button-outline" data-chat-reset>Nulstil chat</button>
      <a class="button button-outline" href="<?= h($this->Url->build('/project/chat-qa')) ?>">Chat QA</a>
      <a class="button button-outline" href="<?= h($this->Url->build('/admin/audit/latest')) ?>">Audit</a>
    </div>
  </section>

  <aside class="admin-list">
    <div class="admin-chat-card">
      <h2 class="admin-section-title">Flow summary</h2>
      <dl class="admin-kv" data-chat-summary></dl>
      <div class="admin-toolbar" data-chat-links></div>
    </div>

    <div class="admin-chat-card">
      <h2 class="admin-section-title">Pipeline preview</h2>
      <div data-chat-preview-message class="admin-muted">Ingen preview endnu.</div>
      <dl class="admin-kv" data-chat-preview></dl>
      <div class="admin-action-list" data-chat-preview-actions></div>
      <div class="admin-blocker-list" data-chat-preview-blockers></div>
    </div>

    <div class="admin-chat-card">
      <h2 class="admin-section-title">Groq forklaring</h2>
      <div data-chat-explanation></div>
    </div>

    <div class="admin-chat-card">
      <h2 class="admin-section-title">Synlige trin</h2>
      <ol class="admin-steps" data-chat-steps></ol>
    </div>

    <div class="admin-chat-card">
      <h2 class="admin-section-title">Regulation citations</h2>
      <div data-chat-citations class="admin-muted">Ingen citations endnu.</div>
    </div>
  </aside>
</div>

<script>
(() => {
  const root = document.querySelector('[data-chat-root]');
  if (!root) return;

  const logEl = root.querySelector('[data-chat-log]');
  const questionEl = root.querySelector('[data-chat-question]');
  const choicesEl = root.querySelector('[data-chat-choices]');
  const summaryEl = root.querySelector('[data-chat-summary]');
  const linksEl = root.querySelector('[data-chat-links]');
  const previewMessageEl = root.querySelector('[data-chat-preview-message]');
  const previewEl = root.querySelector('[data-chat-preview]');
  const previewActionsEl = root.querySelector('[data-chat-preview-actions]');
  const previewBlockersEl = root.querySelector('[data-chat-preview-blockers]');
  const explanationEl = root.querySelector('[data-chat-explanation]');
  const uploadHintEl = root.querySelector('[data-chat-upload-hint]');
  const stepsEl = root.querySelector('[data-chat-steps]');
  const citationsEl = root.querySelector('[data-chat-citations]');
  const formEl = root.querySelector('[data-chat-form]');
  const uploadFormEl = root.querySelector('[data-chat-upload-form]');
  const inputEl = formEl.querySelector('input[name="message"]');
  const resetEl = root.querySelector('[data-chat-reset]');
  const messageUrl = root.dataset.messageUrl;
  const resetUrl = root.dataset.resetUrl;
  const focusUrl = root.dataset.focusUrl;
  const uploadUrl = root.dataset.uploadUrl;
  const csrfToken = root.dataset.csrfToken || '';

  let payload = JSON.parse(root.dataset.initial || '{}');

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function boolLabel(value) {
    return value ? 'ja' : 'nej';
  }

  function renderHistory(history) {
    logEl.innerHTML = '';
    (history || []).forEach((item) => {
      const box = document.createElement('div');
      box.className = 'admin-chat-msg ' + (item.role === 'user' ? 'user' : 'assistant');
      box.innerHTML = '<div class="admin-chat-meta">' + escapeHtml(item.role) + '</div><div>' + escapeHtml(item.content) + '</div>';
      logEl.appendChild(box);
    });
    logEl.scrollTop = logEl.scrollHeight;
  }

  function renderQuestion(question) {
    if (!question) {
      questionEl.textContent = 'Ingen aktive spoergsmaal.';
      choicesEl.innerHTML = '';
      return;
    }
    questionEl.textContent = question.prompt || '';
    choicesEl.innerHTML = '';
    (question.choices || []).forEach((choice) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'admin-chip';
      button.textContent = choice.label || choice.value || '';
      button.addEventListener('click', () => {
        inputEl.value = choice.value || '';
        formEl.requestSubmit();
      });
      choicesEl.appendChild(button);
    });
  }

  function renderSummary(summary, question) {
    const rows = [
      ['travel_state', summary.travel_state || '-'],
      ['ticket_mode', summary.ticket_mode || '-'],
      ['season_mode', boolLabel(!!summary.season_mode)],
      ['operator', summary.operator || '-'],
      ['operator_country', summary.operator_country || '-'],
      ['operator_product', summary.operator_product || '-'],
      ['route', summary.route || '-'],
      ['incident_main', summary.incident_main || '-'],
      ['missed_connection', boolLabel(!!summary.missed_connection)],
      ['delay_minutes', summary.delay_minutes || '-'],
      ['uploaded_file', summary.uploaded_file || '-'],
      ['extraction_provider', summary.extraction_provider || '-'],
      ['extraction_confidence', summary.extraction_confidence || '-'],
      ['eu_only', boolLabel(!!summary.eu_only)],
      ['step2_done', boolLabel(!!summary.step2_done)],
      ['step5_done', boolLabel(!!summary.step5_done)],
      ['gate_art18', boolLabel(!!summary.gate_art18)],
      ['gate_art20', boolLabel(!!summary.gate_art20)],
      ['gate_art20_2c', boolLabel(!!summary.gate_art20_2c)]
    ];
    summaryEl.innerHTML = rows.map(([key, value]) => (
      '<dt>' + escapeHtml(key) + '</dt><dd>' + escapeHtml(value) + '</dd>'
    )).join('');

    const links = [];
    if (question && question.flow_path) {
      links.push('<a class="admin-chip" href="' + escapeHtml(question.flow_path) + '" target="_blank">Aabn aktivt wizard-trin</a>');
    }
    if (summary.datapack_url) {
      links.push('<a class="admin-chip" href="' + escapeHtml(summary.datapack_url) + '" target="_blank">Download data-pack</a>');
    }
    links.push('<a class="admin-chip" href="/flow/start" target="_blank">Aabn flow</a>');
    linksEl.innerHTML = links.join('');
  }

  function renderPreview(preview) {
    const status = preview?.status || 'idle';
    previewMessageEl.textContent = preview?.message || 'Ingen preview endnu.';
    const summary = preview?.summary || {};
    const boolOrUnknown = (value) => {
      if (value === null || value === undefined || value === '') return '-';
      if (value === true) return 'ja';
      if (value === false) return 'nej';
      return String(value);
    };
    const rows = [
      ['status', status],
      ['scope', summary.scope || '-'],
      ['profile_blocked', boolOrUnknown(summary.profile_blocked)],
      ['art12_applies', boolOrUnknown(summary.art12_applies)],
      ['liable_party', summary.liable_party || '-'],
      ['comp_minutes', boolOrUnknown(summary.compensation_minutes)],
      ['comp_pct', summary.compensation_pct === null || summary.compensation_pct === undefined ? '-' : (String(summary.compensation_pct) + '%')],
      ['comp_amount', summary.compensation_amount === null || summary.compensation_amount === undefined || summary.compensation_amount === '' ? '-' : (String(summary.compensation_amount) + ' ' + (summary.currency || 'EUR'))],
      ['refund_eligible', boolOrUnknown(summary.refund_eligible)],
      ['refund_minutes', boolOrUnknown(summary.refund_minutes)],
      ['refusion_outcome', summary.refusion_outcome || '-'],
      ['art20_compliance', boolOrUnknown(summary.art20_compliance)],
      ['gross_claim', summary.gross_claim === null || summary.gross_claim === undefined || summary.gross_claim === '' ? '-' : (String(summary.gross_claim) + ' ' + (summary.currency || 'EUR'))],
      ['claim_basis', summary.claim_basis || '-'],
      ['partial', boolOrUnknown(summary.partial)]
    ];
    previewEl.innerHTML = rows.map(([key, value]) => (
      '<dt>' + escapeHtml(key) + '</dt><dd>' + escapeHtml(value) + '</dd>'
    )).join('');

    const actions = preview?.actions || [];
    previewActionsEl.innerHTML = actions.length ? actions.map((action) => (
      '<div class="admin-action-item">' +
        '<strong>' + escapeHtml(action.label || '') + '</strong>' +
        '<div class="admin-muted">' + escapeHtml(action.detail || '') + '</div>' +
        (action.href ? ('<div style="margin-top:8px;"><a class="admin-chip" href="' + escapeHtml(action.href) + '" target="_blank">Aabn relevant trin</a></div>') : '') +
      '</div>'
    )).join('') : '<div class="admin-muted">Ingen konkrete next actions endnu.</div>';

    const blockers = preview?.blocking_fields || [];
    if (!blockers.length) {
      previewBlockersEl.innerHTML = '<div class="admin-muted">Ingen kendte blocker-felter lige nu.</div>';
      return;
    }
    const priorityLabels = {
      required_now: 'Skal udfyldes nu',
      important_before_export: 'Vigtigt før eksport',
      review_before_export: 'Gennemgå før eksport'
    };
    const priorityOrder = ['required_now', 'important_before_export', 'review_before_export'];
    const grouped = {};
    blockers.forEach((item) => {
      const priority = item.priority || 'review_before_export';
      if (!grouped[priority]) grouped[priority] = [];
      grouped[priority].push(item);
    });
    previewBlockersEl.innerHTML =
      '<div class="admin-muted">Mangler stadig disse felter før claim/export bliver mere robust:</div>' +
      priorityOrder
        .filter((priority) => (grouped[priority] || []).length)
        .map((priority) => (
          '<div class="admin-blocker-group">' +
            '<div class="admin-blocker-group-title">' + escapeHtml(priorityLabels[priority] || priority) + '</div>' +
            grouped[priority].map((item) => (
              '<div class="admin-blocker-item">' +
                '<strong>' + escapeHtml(item.label || item.key || '') + '</strong>' +
                '<div class="admin-muted">' + escapeHtml(item.detail || '') + '</div>' +
                '<div class="admin-muted" style="margin-top:4px;">Gruppe: ' + escapeHtml(item.group || '-') + '</div>' +
                '<div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">' +
                  ((item.can_focus && (item.focus_key || item.key)) ? ('<button type="button" class="admin-chip" data-blocker-focus="' + escapeHtml(item.focus_key || item.key || '') + '">Fokusér i chat</button>') : '') +
                  (item.href ? ('<a class="admin-chip" href="' + escapeHtml(item.href) + '" target="_blank">Aabn relevant trin</a>') : '') +
                '</div>' +
              '</div>'
            )).join('') +
          '</div>'
        )).join('');
  }

  function renderExplanation(explanation) {
    const status = explanation?.status || 'idle';
    const message = explanation?.message || 'Ingen forklaring endnu.';
    const text = explanation?.text || '';
    const provider = explanation?.provider || 'groq';
    const model = explanation?.model || '';
    const statusLabel = {
      ok: 'Live',
      cached: 'Cache',
      disabled: 'Deaktiveret',
      error: 'Fejl',
      idle: 'Idle'
    }[status] || status;
    const meta = [provider, model].filter(Boolean).join(' · ');
    explanationEl.innerHTML =
      '<div class="admin-explanation-status ' + escapeHtml(status) + '">' + escapeHtml(statusLabel) + '</div>' +
      '<div class="admin-muted" style="margin-bottom:10px;">' + escapeHtml(message + (meta ? ' (' + meta + ')' : '')) + '</div>' +
      '<div class="admin-explanation-body">' + escapeHtml(text || 'Ingen forklaring genereret endnu.') + '</div>';
  }

  function renderUploadHint(uploadHint) {
    if (!uploadHint || !uploadHint.text) {
      uploadHintEl.style.display = 'none';
      uploadHintEl.className = 'admin-upload-hint';
      uploadHintEl.innerHTML = '';
      return;
    }
    uploadHintEl.style.display = '';
    uploadHintEl.className = 'admin-upload-hint' + ((uploadHint.tone === 'done') ? ' done' : '');
    uploadHintEl.innerHTML =
      '<strong>' + escapeHtml(uploadHint.title || 'Upload') + '</strong>' +
      '<div class="admin-muted" style="margin-top:6px;">' + escapeHtml(uploadHint.text || '') + '</div>';
  }

  function renderSteps(steps) {
    stepsEl.innerHTML = '';
    (steps || []).forEach((step) => {
      const li = document.createElement('li');
      li.textContent = (step.ui_num ? ('Trin ' + step.ui_num + ': ') : '') + (step.title || '') + (step.state ? (' (' + step.state + ')') : '');
      stepsEl.appendChild(li);
    });
    if (!stepsEl.innerHTML) {
      stepsEl.innerHTML = '<li>Ingen synlige trin.</li>';
    }
  }

  function renderCitations(citations) {
    if (!citations || !citations.length) {
      citationsEl.innerHTML = '<div class="admin-muted">Ingen citations endnu.</div>';
      return;
    }
    citationsEl.innerHTML = citations.map((citation) => (
      '<div class="admin-citation">' +
        '<div><strong>Art. ' + escapeHtml(citation.article) + '</strong> · side ' + escapeHtml(citation.page_from) + '</div>' +
        '<div class="admin-muted">' + escapeHtml(citation.id) + '</div>' +
        '<div style="margin-top:6px;">' + escapeHtml(citation.text) + '</div>' +
      '</div>'
    )).join('');
  }

  function render(nextPayload) {
    payload = nextPayload || {};
    renderHistory(payload.history || []);
    renderQuestion(payload.question || null);
    renderSummary(payload.summary || {}, payload.question || null);
    renderPreview(payload.preview || {});
    renderExplanation(payload.explanation || {});
    renderUploadHint(payload.upload_hint || null);
    renderSteps(payload.visible_steps || []);
    renderCitations(payload.citations || []);
    if (payload.notice) {
      inputEl.placeholder = payload.notice;
    } else {
      inputEl.placeholder = 'Skriv svar eller brug quick replies';
    }
  }

  async function post(url, data) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? {'X-CSRF-Token': csrfToken} : {})
      },
      body: new URLSearchParams(data)
    });
    if (!response.ok) {
      throw new Error('HTTP ' + response.status);
    }
    return response.json();
  }

  async function postUpload(url, formData) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? {'X-CSRF-Token': csrfToken} : {})
      },
      body: formData
    });
    if (!response.ok) {
      throw new Error('HTTP ' + response.status);
    }
    return response.json();
  }

  formEl.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = inputEl.value.trim();
    if (!message) return;
    inputEl.disabled = true;
    try {
      const nextPayload = await post(messageUrl, {message});
      inputEl.value = '';
      render(nextPayload);
    } catch (error) {
      window.alert('Kunne ikke sende besked: ' + error.message);
    } finally {
      inputEl.disabled = false;
      inputEl.focus();
    }
  });

  uploadFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fileInput = uploadFormEl.querySelector('input[name="ticket_upload"]');
    const file = fileInput?.files?.[0];
    if (!file) {
      window.alert('Vælg en fil først.');
      return;
    }
    fileInput.disabled = true;
    try {
      const data = new FormData();
      data.append('ticket_upload', file);
      const nextPayload = await postUpload(uploadUrl, data);
      fileInput.value = '';
      render(nextPayload);
      inputEl.focus();
    } catch (error) {
      window.alert('Kunne ikke uploade filen: ' + error.message);
    } finally {
      fileInput.disabled = false;
    }
  });

  resetEl.addEventListener('click', async () => {
    if (!window.confirm('Nulstil chat og flow-session?')) return;
    try {
      const nextPayload = await post(resetUrl, {});
      render(nextPayload);
      inputEl.focus();
    } catch (error) {
      window.alert('Kunne ikke nulstille chatten: ' + error.message);
    }
  });

  previewBlockersEl.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-blocker-focus]');
    if (!button) return;
    const key = button.getAttribute('data-blocker-focus') || '';
    if (!key) return;
    try {
      const nextPayload = await post(focusUrl, {key});
      render(nextPayload);
      inputEl.focus();
    } catch (error) {
      window.alert('Kunne ikke fokusere blocker i chatten: ' + error.message);
    }
  });

  render(payload);
})();
</script>
