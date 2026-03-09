<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var string $csrfToken */
/** @var array<string,string> $chatUrls */
/** @var array<string,mixed> $initialContext */
?>
<style>
  .passenger-page { max-width: 960px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); margin-bottom: 12px; }
  .history { display: grid; gap: 10px; }
  .bubble { padding: 12px; border-radius: 12px; max-width: 700px; }
  .bubble.assistant { background: #f8fafc; }
  .bubble.user { background: #eff6ff; margin-left: auto; }
  .choices { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
  .choice { border: 1px solid #cbd5e1; background: #fff; border-radius: 999px; padding: 6px 10px; cursor: pointer; }
  .toolbar { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
  .toolbar input[type="text"] { flex: 1; min-width: 260px; }
  .muted { color: #64748b; }
</style>

<div class="passenger-page" id="passenger-chat-root"
     data-csrf-token="<?= h($csrfToken) ?>"
     data-bootstrap-url="<?= h($chatUrls['bootstrap']) ?>"
     data-message-url="<?= h($chatUrls['message']) ?>"
     data-reset-url="<?= h($chatUrls['reset']) ?>"
     data-context-url="<?= h($chatUrls['context']) ?>"
     data-upload-url="<?= h($chatUrls['upload']) ?>"
     data-initial-context='<?= h((string)json_encode($initialContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
  <h1>Hjælp i chat</h1>
  <p class="muted">Denne chat bruger den samme motor som de andre flader, men her er den tilpasset passager-visning. Hvis du åbnede siden fra en rejse eller claim, er den kontekst allerede indlæst.</p>

  <div class="card">
    <strong>Status:</strong> <?= h((string)$snapshot['status']) ?><br>
    <strong>Type:</strong> <?= h($snapshot['mode'] === 'commuter' ? 'Pendler / season pass' : 'Standard') ?>
  </div>

  <div class="card">
    <h2>Samtale</h2>
    <div id="chat-history" class="history"><div class="muted">Indlæser chat...</div></div>
    <div id="chat-choices" class="choices"></div>
    <div class="toolbar">
      <input type="text" id="chat-input" placeholder="Skriv dit svar">
      <button type="button" id="chat-send">Send</button>
      <button type="button" id="chat-reset">Nulstil</button>
    </div>
    <div class="toolbar">
      <input type="file" id="chat-upload" name="ticket_upload">
      <button type="button" id="chat-upload-btn">Upload</button>
    </div>
    <div id="chat-notice" class="muted" style="margin-top:10px;"></div>
  </div>
</div>

<script>
(() => {
  const root = document.getElementById('passenger-chat-root');
  if (!root) return;
  const csrfToken = root.dataset.csrfToken || '';
  const urls = {
    bootstrap: root.dataset.bootstrapUrl || '',
    message: root.dataset.messageUrl || '',
    reset: root.dataset.resetUrl || '',
    context: root.dataset.contextUrl || '',
    upload: root.dataset.uploadUrl || '',
  };
  const initialContext = JSON.parse(root.dataset.initialContext || '{}');

  const historyEl = document.getElementById('chat-history');
  const choicesEl = document.getElementById('chat-choices');
  const noticeEl = document.getElementById('chat-notice');
  const inputEl = document.getElementById('chat-input');
  const uploadEl = document.getElementById('chat-upload');

  const esc = (value) => {
    const span = document.createElement('span');
    span.textContent = value == null ? '' : String(value);
    return span.innerHTML;
  };

  const fetchJson = async (url, options = {}) => {
    const headers = {
      ...(csrfToken ? {'X-CSRF-Token': csrfToken} : {}),
      ...(options.headers || {}),
    };
    const response = await fetch(url, {...options, headers});
    return response.json();
  };

  const render = (payload) => {
    const history = Array.isArray(payload?.history) ? payload.history : [];
    const question = payload?.question || null;
    const notice = payload?.notice || '';
    historyEl.innerHTML = history.length === 0
      ? '<div class="muted">Ingen beskeder endnu.</div>'
      : history.map((entry) => `
          <div class="bubble ${entry.role === 'user' ? 'user' : 'assistant'}">${esc(entry.content || '')}</div>
        `).join('');

    const choices = Array.isArray(question?.choices) ? question.choices : [];
    choicesEl.innerHTML = choices.map((choice) => {
      const value = esc(choice.value || '');
      const label = esc(choice.label || choice.value || '');
      return `<button type="button" class="choice" data-value="${value}">${label}</button>`;
    }).join('');

    noticeEl.textContent = notice;
  };

  const sendMessage = async (message) => {
    const trimmed = (message || '').trim();
    if (!trimmed) return;
    noticeEl.textContent = 'Sender...';
    const body = new URLSearchParams({message: trimmed});
    const payload = await fetchJson(urls.message, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body,
    });
    inputEl.value = '';
    render(payload);
  };

  const bootstrap = async () => {
    const payload = await fetchJson(urls.bootstrap);
    render(payload);
    if (initialContext && Object.keys(initialContext).length > 0) {
      const contextPayload = await fetchJson(urls.context, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({context: initialContext}),
      });
      render(contextPayload);
    }
  };

  document.getElementById('chat-send')?.addEventListener('click', () => sendMessage(inputEl.value));
  inputEl?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      sendMessage(inputEl.value);
    }
  });
  choicesEl?.addEventListener('click', (event) => {
    const target = event.target.closest('[data-value]');
    if (!target) return;
    sendMessage(target.getAttribute('data-value') || '');
  });
  document.getElementById('chat-reset')?.addEventListener('click', async () => {
    noticeEl.textContent = 'Nulstiller...';
    const payload = await fetchJson(urls.reset, {method: 'POST'});
    render(payload);
  });
  document.getElementById('chat-upload-btn')?.addEventListener('click', async () => {
    const file = uploadEl?.files?.[0];
    if (!file) {
      noticeEl.textContent = 'Vælg en fil først.';
      return;
    }
    noticeEl.textContent = 'Uploader...';
    const formData = new FormData();
    formData.append('ticket_upload', file);
    const payload = await fetchJson(urls.upload, {
      method: 'POST',
      body: formData,
    });
    render(payload);
  });

  bootstrap();
})();
</script>
