/**
 * Progressive question disclosure for explicitly marked AIR TC6 forms.
 * All questions remain in the HTML and are hidden only after validation.
 */
(() => {
  const rootSelector = '[data-air-progressive-form]';
  const groupSelector = '[data-progressive-group]';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  const splitList = (value) => String(value || '').split(',')
    .map((item) => item.trim()).filter(Boolean);

  const fieldNodes = (root, name) => Array.from(root.elements || [])
    .filter((field) => field && field.name === name);

  const fieldValue = (root, name) => {
    const fields = fieldNodes(root, name);
    const checked = fields.find((field) =>
      (field.type === 'radio' || field.type === 'checkbox') && field.checked
    );
    if (checked) return String(checked.value || '1');
    const field = fields.find((candidate) =>
      candidate.type !== 'radio' && candidate.type !== 'checkbox'
    );
    return field ? String(field.value || '').trim() : '';
  };

  const fieldComplete = (root, name) => {
    const fields = fieldNodes(root, name);
    if (!fields.length) return false;
    if (fields.some((field) => field.type === 'radio')) {
      return fields.some((field) => field.type === 'radio' && field.checked);
    }
    if (fields.some((field) => field.type === 'checkbox')) {
      return fields.some((field) => field.type === 'checkbox' && field.checked);
    }
    const field = fields.find((candidate) =>
      candidate.type !== 'hidden' || String(candidate.value || '').trim() !== ''
    ) || fields[0];
    if (!field || String(field.value || '').trim() === '') return false;
    return typeof field.checkValidity !== 'function' || field.checkValidity();
  };

  const matchesCondition = (root, spec) => {
    if (!spec) return true;
    return String(spec).split(';').every((clause) => {
      const separator = clause.indexOf(':');
      if (separator < 1) return false;
      const name = clause.slice(0, separator).trim();
      const accepted = splitList(clause.slice(separator + 1));
      return accepted.includes(fieldValue(root, name));
    });
  };

  const clearField = (root, name) => {
    fieldNodes(root, name).forEach((field) => {
      if (field.type === 'radio' || field.type === 'checkbox') {
        field.checked = false;
      } else if (field.tagName === 'SELECT') {
        field.value = '';
        if (field.value !== '') field.selectedIndex = 0;
      } else {
        field.value = '';
      }
      if (typeof field.setCustomValidity === 'function') field.setCustomValidity('');
    });
  };

  const containsError = (nodes) => nodes.some((node) =>
    node.matches('.error, .form-error, [aria-invalid="true"]') ||
    node.querySelector('.error, .error-message, .form-error, [aria-invalid="true"]')
  );

  const setNodeVisible = (node, visible, animate, initialized) => {
    const wasVisible = !node.hidden;
    node.hidden = !visible;
    node.setAttribute('aria-hidden', visible ? 'false' : 'true');
    if (visible && !wasVisible && initialized && animate && !reducedMotion.matches) {
      node.classList.add('air-progressive-revealing');
      window.setTimeout(() => node.classList.remove('air-progressive-revealing'), 220);
    } else if (!visible) {
      node.classList.remove('air-progressive-revealing');
    }
  };

  const buildGroups = (root) => {
    const ordered = [];
    const byName = new Map();
    root.querySelectorAll(groupSelector).forEach((node) => {
      const name = String(node.dataset.progressiveGroup || '').trim();
      if (!byName.has(name)) {
        const group = { name, nodes: [], revealed: false };
        byName.set(name, group);
        ordered.push(group);
      }
      byName.get(name).nodes.push(node);
    });
    return ordered;
  };

  const validateContract = (root, groups) => {
    if (groups.length < 2 || groups.some((group) => !group.name)) return false;
    return groups.every((group) => group.nodes.every((node) => {
      const condition = node.dataset.progressiveShowIf || '';
      if (condition) {
        const names = String(condition).split(';')
          .map((clause) => clause.split(':')[0].trim());
        if (names.some((name) => !name || !fieldNodes(root, name).length)) return false;
      }
      return splitList(node.dataset.progressiveFields)
        .every((name) => fieldNodes(root, name).length > 0);
    }));
  };

  const initialize = (root) => {
    if (!(root instanceof HTMLFormElement) || root.dataset.airProgressiveBound === 'true') return;
    const groups = buildGroups(root);
    if (!validateContract(root, groups)) {
      root.querySelectorAll(groupSelector).forEach((node) => {
        node.hidden = false;
        node.removeAttribute('aria-hidden');
      });
      return;
    }

    root.dataset.airProgressiveBound = 'true';
    let initialized = false;
    const evaluate = (animate = true) => {
      groups.forEach((group) => {
        group.activeNodes = group.nodes.filter((node) =>
          matchesCondition(root, node.dataset.progressiveShowIf)
        );
        group.nodes.filter((node) => !group.activeNodes.includes(node)).forEach((node) => {
          splitList(node.dataset.progressiveClear).forEach((name) => clearField(root, name));
        });
        const fields = Array.from(new Set(group.activeNodes.flatMap((node) =>
          splitList(node.dataset.progressiveFields)
        )));
        group.complete = group.activeNodes.length > 0 && (
          group.activeNodes.every((node) => node.dataset.progressiveComplete === 'always') ||
          (fields.length > 0 && fields.every((name) => fieldComplete(root, name)))
        );
        group.hasError = containsError(group.activeNodes);
      });

      const activeGroups = groups.filter((group) => group.activeNodes.length > 0);
      let unlockNext = true;
      const errorIndex = activeGroups.reduce(
        (last, group, index) => group.hasError ? index : last,
        -1
      );
      activeGroups.forEach((group, index) => {
        const shouldReveal = unlockNext || group.revealed || index <= errorIndex;
        if (shouldReveal) group.revealed = true;
        if (!group.complete && unlockNext) unlockNext = false;
      });

      groups.forEach((group) => {
        const groupVisible = group.revealed && group.activeNodes.length > 0;
        group.nodes.forEach((node) => {
          const active = group.activeNodes.includes(node);
          setNodeVisible(node, groupVisible && active, animate, initialized);
          node.classList.toggle(
            'air-progressive-answered',
            groupVisible && group.complete && node === group.activeNodes[0] &&
              node.dataset.progressiveComplete !== 'always'
          );
        });
      });

      root.classList.add('air-progressive-ready');
      root.dispatchEvent(new CustomEvent('air:progressive-updated', {
        bubbles: true,
        detail: { activeGroups: activeGroups.length },
      }));
      initialized = true;
    };

    root.addEventListener('input', () => evaluate(true));
    root.addEventListener('change', () => evaluate(true));
    evaluate(false);
  };

  const boot = () => document.querySelectorAll(rootSelector).forEach(initialize);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
