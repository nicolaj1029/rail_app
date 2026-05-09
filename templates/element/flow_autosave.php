<?php
/** @var \App\View\AppView $this */
$step = strtolower(trim((string)($step ?? '')));
$formSelector = trim((string)($formSelector ?? 'form'));
$autosaveUrl = $this->Url->build([
    'controller' => 'Flow',
    'action' => 'autosave',
    '?' => ['step' => $step],
]);
?>
<?php if ($step !== ''): ?>
<script>
(() => {
  const form = document.querySelector(<?= json_encode($formSelector) ?>);
  if (!form) return;

  const autosaveUrl = <?= json_encode($autosaveUrl) ?>;
  let saveTimer = null;
  let activeSave = null;

  const buildPayload = () => {
    const fd = new FormData(form);
    fd.append('_step', <?= json_encode($step) ?>);
    fd.append('_autosave', '1');

    form.querySelectorAll('input[type="file"]').forEach((input) => {
      if (input.name) {
        fd.delete(input.name);
      }
    });

    form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      if (!input.name || input.disabled) return;
      if (!fd.has(input.name)) {
        fd.append(input.name, '');
      }
    });

    return fd;
  };

  const saveDraft = async () => {
    if (activeSave) {
      try { await activeSave; } catch (e) { /* ignore */ }
    }
    activeSave = fetch(autosaveUrl, {
      method: 'POST',
      body: buildPayload(),
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    }).catch(() => null).finally(() => {
      activeSave = null;
    });
    return activeSave;
  };

  const flushSave = async () => {
    clearTimeout(saveTimer);
    try {
      await Promise.race([
        saveDraft(),
        new Promise((resolve) => window.setTimeout(resolve, 1200))
      ]);
    } catch (e) {
      /* ignore */
    }
  };

  const queueSave = () => {
    clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => { void saveDraft(); }, 180);
  };

  const saveImmediately = () => {
    clearTimeout(saveTimer);
    window.setTimeout(() => { void saveDraft(); }, 0);
  };

  const beaconSave = () => {
    try {
      const body = buildPayload();
      if (navigator.sendBeacon) {
        navigator.sendBeacon(autosaveUrl, body);
      }
    } catch (e) {
      /* ignore */
    }
  };

  form.querySelectorAll('input[type="radio"], input[type="checkbox"], select').forEach((field) => {
    field.addEventListener('change', saveImmediately);
  });

  form.querySelectorAll('input:not([type="radio"]):not([type="checkbox"]):not([type="file"]):not([type="hidden"]), textarea').forEach((field) => {
    field.addEventListener('blur', queueSave);
  });

  document.querySelectorAll('a.button[href], a.flow-stepper__link[href]').forEach((link) => {
    link.addEventListener('click', async (event) => {
      event.preventDefault();
      await flushSave();
      window.location.href = link.href;
    });
  });

  window.addEventListener('pagehide', () => {
    beaconSave();
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      beaconSave();
    }
  });
})();
</script>
<?php endif; ?>
