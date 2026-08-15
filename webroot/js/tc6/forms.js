/**
 * Shared TC6 form behaviors.
 *
 * Keep this file focused on small, reusable DOM behaviors that do not own
 * legal gating, calculations, or server-side progression.
 */

(() => {
  const tc6Forms = window.tc6Forms || {};

  tc6Forms.setBlockVisible = tc6Forms.setBlockVisible || ((el, show) => {
    if (!el) return;
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  });

  tc6Forms.showById = tc6Forms.showById || ((id, show) => {
    const el = document.getElementById(id);
    if (!el) return;
    tc6Forms.setBlockVisible(el, show);
  });

  tc6Forms.getRadioValue = tc6Forms.getRadioValue || ((name, root = document) => {
    const checked = root.querySelector(`input[name="${name}"]:checked`);
    return checked ? (checked.value || '') : '';
  });

  tc6Forms.getFieldValue = tc6Forms.getFieldValue || ((name, root = document) => {
    const checked = root.querySelector(`input[name="${name}"]:checked`);
    if (checked) return checked.value || '';
    const select = root.querySelector(`select[name="${name}"]`);
    if (select) return select.value || '';
    const input = root.querySelector(`input[name="${name}"]`);
    if (input && input.type !== 'radio' && input.type !== 'checkbox') {
      return input.value || '';
    }
    return '';
  });

  tc6Forms.clearField = tc6Forms.clearField || ((name, root = document) => {
    root.querySelectorAll(`[name="${name}"]`).forEach((el) => {
      if (el.type === 'radio' || el.type === 'checkbox') {
        el.checked = false;
        return;
      }
      if (el.tagName === 'SELECT') {
        el.value = '';
        if (el.value !== '') {
          el.selectedIndex = 0;
        }
        return;
      }
      el.value = '';
    });
  });

  tc6Forms.clearFields = tc6Forms.clearFields || ((names, root = document) => {
    names.forEach((name) => tc6Forms.clearField(name, root));
  });

  tc6Forms.updateShowIf = tc6Forms.updateShowIf || ((root = document) => {
    root.querySelectorAll('[data-show-if]').forEach((el) => {
      const spec = el.getAttribute('data-show-if');
      if (!spec) return;
      const parts = spec.split(':');
      if (parts.length !== 2) return;
      const name = parts[0];
      const valid = parts[1].split(',');
      const value = tc6Forms.getFieldValue(name, root);
      const show = value !== '' && valid.includes(value);
      tc6Forms.setBlockVisible(el, show);
    });
  });

  window.tc6Forms = tc6Forms;
})();

document.addEventListener('DOMContentLoaded', () => {
  const airIncidentForm = document.querySelector('[data-tc6-air-incident]');
  if (airIncidentForm) {
    const delaySection = document.getElementById('t6aiDelaySection');
    const cancelSection = document.getElementById('t6aiCancellationSection');
    const deniedSection = document.getElementById('t6aiDeniedSection');

    const showAirIncidentSection = (mode) => {
      if (delaySection) {
        delaySection.classList.toggle('t6ai-hidden', mode !== 'delay');
      }
      if (cancelSection) {
        cancelSection.classList.toggle('t6ai-hidden', mode !== 'cancellation');
      }
      if (deniedSection) {
        deniedSection.classList.toggle('t6ai-hidden', mode !== 'denied_boarding');
      }
    };

    airIncidentForm.querySelectorAll('input[name="incident_main"]').forEach((input) => {
      input.addEventListener('change', () => {
        if (input.checked) {
          showAirIncidentSection(input.value || '');
        }
      });
    });

    const initial = airIncidentForm.querySelector('input[name="incident_main"]:checked');
    showAirIncidentSection(initial ? (initial.value || '') : '');
  }

  const downgradeTicketSelect = document.getElementById('tc6DowngradeTicketSelect');
  if (downgradeTicketSelect) {
    downgradeTicketSelect.addEventListener('change', () => {
      const value = downgradeTicketSelect.value || '';
      const url = new URL(window.location.href);
      if (value) {
        url.searchParams.set('ticket', value);
      } else {
        url.searchParams.delete('ticket');
      }
      url.searchParams.set('tc6', '1');
      window.location.href = url.toString();
    });
  }
});
