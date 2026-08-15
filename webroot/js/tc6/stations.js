/**
 * Thin station autocomplete for the existing CakePHP endpoint.
 *
 * Reads from:
 *   /api/stations/search?q=...
 *
 * Writes back to the same field contract the rail flow already uses:
 * - fieldName
 * - lookupPrefix_lookup_id
 * - lookupPrefix_lookup_code
 * - lookupPrefix_lookup_source
 * - lookupPrefix_lookup_country
 */

document.addEventListener('alpine:init', () => {
  Alpine.data('tc6StationAutocomplete', (config = {}) => ({
    endpoint: config.endpoint || '/api/stations/search',
    inputName: config.inputName || 'station',
    lookupPrefix: config.lookupPrefix || config.inputName || 'station',
    placeholder: config.placeholder || 'Soeg station...',
    query: config.initial || '',
    selectedId: config.initialId || '',
    selectedCode: config.initialCode || '',
    selectedSource: config.initialSource || '',
    selectedCountry: config.initialCountry || '',
    canonicalStationId: config.initialCanonicalStationId || '',
    providerStationId: config.initialProviderStationId || '',
    canonicalName: config.initialCanonicalName || config.initial || '',
    displayName: config.initialDisplayName || config.initial || '',
    typedStationIdsJson: config.initialTypedStationIdsJson || '',
    providerIdsJson: config.initialProviderIdsJson || '',
    sourceMetadataJson: config.initialSourceMetadataJson || '',
    selectedName: config.initial || '',
    results: [],
    loading: false,
    open: false,
    identityError: false,
    focusIdx: -1,
    debounceTimer: null,
    abortController: null,
    requestToken: 0,

    init() {
      if (this.query) {
        this.selectedName = this.query;
      }
      this.$nextTick(() => {
        const form = this.$root?.closest('form');
        if (!form) {
          return;
        }
        form.addEventListener('submit', (event) => {
          if (!this.hasAtomicIdentity()) {
            event.preventDefault();
            this.identityError = true;
            this.$refs?.stationInput?.setCustomValidity('Vælg en station fra listen.');
            this.$refs?.stationInput?.reportValidity();
          }
        });
      });
    },

    onInput() {
      this.requestToken += 1;
      if (this.abortController) {
        this.abortController.abort();
        this.abortController = null;
      }
      this.selectedId = '';
      this.selectedCode = '';
      this.selectedSource = '';
      this.selectedCountry = '';
      this.canonicalStationId = '';
      this.providerStationId = '';
      this.canonicalName = '';
      this.displayName = '';
      this.typedStationIdsJson = '';
      this.providerIdsJson = '';
      this.sourceMetadataJson = '';
      this.selectedName = this.query;
      this.identityError = false;
      this.$refs?.stationInput?.setCustomValidity('');

      clearTimeout(this.debounceTimer);
      if (this.query.length < 2) {
        this.results = [];
        this.open = false;
        return;
      }

      this.debounceTimer = setTimeout(() => this.search(), 180);
    },

    async search() {
      const queryAtStart = String(this.query || '').trim();
      const token = ++this.requestToken;
      if (this.abortController) {
        this.abortController.abort();
      }
      this.abortController = new AbortController();
      this.loading = true;
      try {
        const separator = this.endpoint.includes('?') ? '&' : '?';
        const url = `${this.endpoint}${separator}q=${encodeURIComponent(queryAtStart)}&limit=8`;
        const res = await fetch(url, {
          signal: this.abortController.signal,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) {
          throw new Error('Search failed');
        }
        const payload = await res.json();
        const data = Array.isArray(payload)
          ? payload
          : Array.isArray(payload?.data?.stations)
            ? payload.data.stations
            : [];

        if (token !== this.requestToken || queryAtStart !== String(this.query || '').trim()) {
          window.__railStationStaleResponses = (window.__railStationStaleResponses || 0) + 1;
          return;
        }

        this.results = data.slice(0, 8);
        this.open = this.results.length > 0;
        this.focusIdx = -1;
        const exact = this.unambiguousExactMatch(this.results, queryAtStart);
        if (exact) {
          this.select(exact, { keepOpen: true });
          this.results = data.slice(0, 8);
          this.open = this.results.length > 0;
        }
      } catch (error) {
        if (error && error.name === 'AbortError') {
          return;
        }
        if (token !== this.requestToken) {
          return;
        }
        this.results = [];
        this.open = false;
      } finally {
        if (token === this.requestToken) {
          this.loading = false;
          this.abortController = null;
        }
      }
    },

    select(station, options = {}) {
      const typedIds = station.typed_station_ids && typeof station.typed_station_ids === 'object'
        ? station.typed_station_ids
        : {};
      const providerIds = station.provider_ids && typeof station.provider_ids === 'object'
        ? station.provider_ids
        : {};
      const sourceMetadata = station.source_metadata && typeof station.source_metadata === 'object'
        ? station.source_metadata
        : {};
      this.canonicalStationId = station.canonical_station_id || '';
      this.providerStationId = station.provider_station_id || station.id || '';
      this.selectedId = this.canonicalStationId || station.id || '';
      this.selectedCode = station.code || station.uic || '';
      this.selectedSource = station.source || '';
      this.selectedCountry = station.country || '';
      this.canonicalName = station.canonical_name || station.name || station.label || '';
      this.displayName = station.display_name || station.label || station.name || '';
      this.typedStationIdsJson = Object.keys(typedIds).length ? JSON.stringify(typedIds) : '';
      this.providerIdsJson = Object.keys(providerIds).length ? JSON.stringify(providerIds) : '';
      this.sourceMetadataJson = Object.keys(sourceMetadata).length ? JSON.stringify(sourceMetadata) : '';
      this.selectedName = this.displayName;
      this.query = this.displayName;
      this.identityError = false;
      this.$refs?.stationInput?.setCustomValidity('');
      if (!options.keepOpen) {
        this.open = false;
        this.results = [];
      }

      this.$dispatch('tc6-station-selected', {
        inputName: this.inputName,
        lookupPrefix: this.lookupPrefix,
        id: this.selectedId,
        canonicalStationId: this.canonicalStationId,
        providerStationId: this.providerStationId,
        code: this.selectedCode,
        source: this.selectedSource,
        country: this.selectedCountry,
        name: this.selectedName,
        typedStationIds: typedIds,
        providerIds,
        sourceMetadata,
      });
    },

    hasAtomicIdentity() {
      return String(this.query || '').trim() === '' || String(this.canonicalStationId || '').trim() !== '';
    },

    normalize(value) {
      return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
    },

    unambiguousExactMatch(stations, query) {
      const needle = this.normalize(query);
      if (!needle) {
        return null;
      }
      const exact = new Map();
      (Array.isArray(stations) ? stations : []).forEach((station) => {
        const label = station.display_name || station.label || station.name || '';
        if (this.normalize(label) !== needle || !station.auto_select_eligible) {
          return;
        }
        const identity = String(station.canonical_station_id || '').trim();
        if (identity) {
          exact.set(identity, station);
        }
      });
      return exact.size === 1 ? Array.from(exact.values())[0] : null;
    },

    onKeydown(event) {
      if (!this.open) {
        return;
      }

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        this.focusIdx = Math.min(this.focusIdx + 1, this.results.length - 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        this.focusIdx = Math.max(this.focusIdx - 1, 0);
      } else if (event.key === 'Enter' && this.focusIdx >= 0) {
        event.preventDefault();
        this.select(this.results[this.focusIdx]);
      } else if (event.key === 'Escape') {
        this.open = false;
      }
    },

    onBlur() {
      setTimeout(() => {
        this.open = false;
      }, 180);
    },
  }));
});

(function () {
  function normalizeNeedle(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function resolveTc6BasePath() {
    const asset = Array.from(document.scripts).find((script) => {
      const src = script.getAttribute('src') || '';
      return /\/js\/tc6\/stations\.js(?:\?|$)/.test(src);
    });
    if (!asset || !asset.src) {
      return '';
    }
    const url = new URL(asset.src, window.location.href);
    return url.pathname.replace(/\/js\/tc6\/stations\.js$/, '');
  }

  function bootTc6FerryEntitlementsAutocomplete() {
    const wrap = document.querySelector('.tc6-ferry-entitlements-wrap, .tc6-air-entitlements-wrap');
    const form = document.getElementById('entitlementsForm');
    if (!wrap || !form) {
      return;
    }

    const basePath = resolveTc6BasePath();
    const endpoint = new URL((basePath || '') + '/api/transport-nodes/search', window.location.origin);
    const transportMode = wrap.classList.contains('tc6-air-entitlements-wrap') ? 'air' : 'ferry';
    const inputNames = transportMode === 'air'
      ? ['dep_station', 'arr_station']
      : ['dep_station', 'arr_station', 'dep_terminal', 'arr_terminal'];
    const inputs = inputNames
      .map((name) => form.querySelector(`input[name="${name}"]`))
      .filter((node) => node instanceof HTMLInputElement);

    if (!inputs.length) {
      return;
    }

    const prefixMap = {
      dep_station: 'dep_station_lookup_',
      arr_station: 'arr_station_lookup_',
      dep_terminal: 'dep_terminal_lookup_',
      arr_terminal: 'arr_terminal_lookup_',
    };
    const state = new WeakMap();

    function metaFields(name) {
      const prefix = prefixMap[name];
      if (!prefix) {
        return {};
      }
      return {
        id: form.querySelector(`input[name="${prefix}id"]`),
        code: form.querySelector(`input[name="${prefix}code"]`),
        mode: form.querySelector(`input[name="${prefix}mode"]`),
        country: form.querySelector(`input[name="${prefix}country"]`),
        inEu: form.querySelector(`input[name="${prefix}in_eu"]`),
        nodeType: form.querySelector(`input[name="${prefix}node_type"]`),
        parent: form.querySelector(`input[name="${prefix}parent"]`),
        source: form.querySelector(`input[name="${prefix}source"]`),
        lat: form.querySelector(`input[name="${prefix}lat"]`),
        lon: form.querySelector(`input[name="${prefix}lon"]`),
      };
    }

    function clearLookupMeta(name) {
      Object.values(metaFields(name)).forEach((field) => {
        if (field) {
          field.value = '';
        }
      });
    }

    function setLookupMeta(name, node) {
      const meta = metaFields(name);
      if (meta.id) meta.id.value = node?.id ? String(node.id) : '';
      if (meta.code) meta.code.value = node?.code ? String(node.code) : '';
      if (meta.mode) meta.mode.value = node?.mode ? String(node.mode) : transportMode;
      if (meta.country) meta.country.value = node?.country ? String(node.country) : '';
      if (meta.inEu) meta.inEu.value = node?.in_eu === true ? 'yes' : (node?.in_eu === false ? 'no' : '');
      if (meta.nodeType) meta.nodeType.value = node?.node_type ? String(node.node_type) : '';
      if (meta.parent) meta.parent.value = node?.parent_name ? String(node.parent_name) : '';
      if (meta.source) meta.source.value = node?.source ? String(node.source) : '';
      if (meta.lat) meta.lat.value = node?.lat !== undefined && node?.lat !== null ? String(node.lat) : '';
      if (meta.lon) meta.lon.value = node?.lon !== undefined && node?.lon !== null ? String(node.lon) : '';
    }

    function ensureBox(input) {
      if (!input.dataset.nodeSuggestOwner) {
        input.dataset.nodeSuggestOwner = `node-suggest-${Math.random().toString(36).slice(2)}`;
      }
      let box = document.body.querySelector(`.node-suggest.portal[data-owner="${input.dataset.nodeSuggestOwner}"]`);
      if (!box) {
        box = document.createElement('div');
        box.className = 'node-suggest portal';
        box.id = input.dataset.nodeSuggestOwner;
        box.setAttribute('role', 'listbox');
        box.dataset.owner = input.dataset.nodeSuggestOwner;
        box.dataset.for = input.name;
        box.style.display = 'none';
        document.body.appendChild(box);
      }
      input.setAttribute('role', 'combobox');
      input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-controls', box.id);
      if (transportMode === 'air') {
        input.dataset.airportPreselectorBound = 'true';
      }
      return box;
    }

    function hide(box) {
      if (!box) {
        return;
      }
      box.style.display = 'none';
      box.innerHTML = '';
    }

    function positionBox(box, input) {
      if (!box || !input) {
        return;
      }
      const rect = input.getBoundingClientRect();
      const estimatedHeight = 220;
      const belowTop = Math.round(rect.bottom + 2);
      const top = belowTop + estimatedHeight > window.innerHeight
        ? Math.max(8, Math.round(rect.top - estimatedHeight - 2))
        : belowTop;
      box.style.left = `${Math.round(rect.left)}px`;
      box.style.top = `${top}px`;
      box.style.width = `${Math.round(rect.width)}px`;
    }

    function nodeTypeLabel(nodeType) {
      const value = String(nodeType || '').toLowerCase();
      if (value === 'airport') return 'lufthavn';
      if (value === 'port') return 'havn';
      if (value === 'ferry_terminal' || value === 'terminal') return 'faergeterminal';
      if (value === 'cruise_terminal') return 'krydstogtterminal';
      return value ? value.replace(/_/g, ' ') : '';
    }

    function applySelection(input, node) {
      if (!node || !node.name) {
        return;
      }
      input.value = String(node.name);
      input.setCustomValidity('');
      setLookupMeta(input.name, node);

      if ((input.name === 'dep_terminal' || input.name === 'arr_terminal') && node.parent_name) {
        const portFieldName = input.name === 'dep_terminal' ? 'dep_station' : 'arr_station';
        const portField = form.querySelector(`input[name="${portFieldName}"]`);
        const portIdField = metaFields(portFieldName).id;
        if (portField && String(portField.value || '').trim() === '') {
          portField.value = String(node.parent_name);
          if (portIdField) {
            portIdField.value = '';
          }
        }
      }

      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function previewTopMatch(input, node, typedValue) {
      const raw = String(typedValue || '').trim();
      const name = String(node?.name || '').trim();
      if (!raw || !name) {
        return;
      }

      const rawNeedle = normalizeNeedle(raw);
      const nameNeedle = normalizeNeedle(name);
      if (!nameNeedle.startsWith(rawNeedle)) {
        return;
      }

      input.value = name;
      if (typeof input.setSelectionRange === 'function' && name.length >= raw.length) {
        input.setSelectionRange(raw.length, name.length);
      }
    }

    function render(box, input, nodes) {
      box.innerHTML = '';
      if (!nodes.length) {
        hide(box);
        return;
      }

      nodes.slice(0, 10).forEach((node, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('role', 'option');
        if (index === 0) {
          button.classList.add('is-preselected');
        }
        button.appendChild(document.createTextNode(node.name || '(ukendt sted)'));

        const metaBits = [];
        if (node.code) metaBits.push(String(node.code));
        if (node.country) metaBits.push(String(node.country));
        if (node.in_eu === true) metaBits.push('EU');
        else if (node.in_eu === false && node.country) metaBits.push('ikke-EU');
        if (node.parent_name) metaBits.push(String(node.parent_name));
        if (node.node_type) metaBits.push(nodeTypeLabel(node.node_type));
        if (metaBits.length) {
          const meta = document.createElement('div');
          meta.className = 'muted';
          meta.textContent = metaBits.join(' · ');
          button.appendChild(document.createElement('br'));
          button.appendChild(meta);
        }

        button.addEventListener('click', () => {
          applySelection(input, node);
          hide(box);
        });
        box.appendChild(button);
      });

      positionBox(box, input);
      box.style.display = 'block';
    }

    async function fetchNodes(input, box) {
      const localState = state.get(input);
      if (!localState) {
        return;
      }

      const rawQuery = String(localState.rawQuery || '').trim();
      if (rawQuery.length < 2) {
        localState.lastNodes = [];
        hide(box);
        return;
      }

      if (localState.ctrl) {
        try {
          localState.ctrl.abort();
        } catch (_) {
          // noop
        }
      }
      localState.ctrl = new AbortController();

      const url = new URL(endpoint.toString());
      url.searchParams.set('mode', transportMode);
      url.searchParams.set('q', rawQuery);
      url.searchParams.set('limit', '10');
      url.searchParams.set(
        'kind',
        transportMode === 'air'
          ? 'airport'
          : (input.name === 'dep_terminal' || input.name === 'arr_terminal' ? 'terminal' : 'port')
      );

      try {
        const res = await fetch(url.toString(), {
          signal: localState.ctrl.signal,
          headers: { Accept: 'application/json' },
        });
        if (!res.ok) {
          throw new Error('transport_node_search_failed');
        }
        const payload = await res.json();
        const nodes = Array.isArray(payload?.data?.nodes) ? payload.data.nodes : [];
        localState.lastNodes = nodes;
        render(box, input, nodes);
        if (document.activeElement === input && nodes[0] && nodes[0].name) {
          previewTopMatch(input, nodes[0], rawQuery);
        }
      } catch (_) {
        localState.lastNodes = [];
        hide(box);
      }
    }

    inputs.forEach((input) => {
      const box = ensureBox(input);
      const localState = { timer: null, ctrl: null, lastNodes: [], rawQuery: '' };
      state.set(input, localState);

      input.addEventListener('input', () => {
        localState.rawQuery = String(input.value || '').trim();
        localState.lastNodes = [];
        clearLookupMeta(input.name);
        if (localState.timer) {
          clearTimeout(localState.timer);
        }
        localState.timer = window.setTimeout(() => fetchNodes(input, box), transportMode === 'air' ? 100 : 180);
      });

      input.addEventListener('focus', () => {
        if (box.innerHTML.trim() !== '') {
          positionBox(box, input);
          box.style.display = 'block';
        }
      });

      input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          hide(box);
          return;
        }
        if (event.key === 'Enter' || event.key === 'Tab') {
          const topNode = Array.isArray(localState.lastNodes) ? localState.lastNodes[0] || null : null;
          if (topNode && topNode.name) {
            if (event.key === 'Enter') {
              event.preventDefault();
            }
            applySelection(input, topNode);
            hide(box);
          }
        }
      });

      input.addEventListener('blur', () => {
        const topNode = Array.isArray(localState.lastNodes) ? localState.lastNodes[0] || null : null;
        const idField = metaFields(input.name).id;
        if (topNode && topNode.name && idField && String(idField.value || '').trim() === '') {
          applySelection(input, topNode);
        }
        window.setTimeout(() => hide(box), 180);
      });

      window.addEventListener('resize', () => {
        if (box.style.display !== 'none') {
          positionBox(box, input);
        }
      });
    });

    if (transportMode === 'air') {
      form.addEventListener('submit', (event) => {
        for (const [name, message] of [
          ['dep_station', 'Vælg en gyldig afgangslufthavn fra listen.'],
          ['arr_station', 'Vælg en gyldig ankomstlufthavn fra listen.'],
        ]) {
          const input = form.querySelector(`input[name="${name}"]`);
          const idField = metaFields(name).id;
          if (input instanceof HTMLInputElement && (!idField || String(idField.value || '').trim() === '')) {
            event.preventDefault();
            input.setCustomValidity(message);
            input.reportValidity();
            input.focus();
            break;
          }
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootTc6FerryEntitlementsAutocomplete, { once: true });
  } else {
    bootTc6FerryEntitlementsAutocomplete();
  }
})();
