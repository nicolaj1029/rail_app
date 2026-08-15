/**
 * Thin Alpine helpers for reveal and UI polish only.
 * No gates, no calculations, no client-side flow progression.
 */

document.addEventListener('alpine:init', () => {
  Alpine.data('tc6Reveal', (options = {}) => ({
    selected: options.initial || null,
    open: options.open || false,

    select(value) {
      this.selected = value;
    },

    is(value) {
      return this.selected === value;
    },

    isAny(...values) {
      return values.includes(this.selected);
    },

    toggle() {
      this.open = !this.open;
    },
  }));

  Alpine.data('tc6MultiSelect', (name = 'items', initial = []) => ({
    selected: new Set(initial),
    fieldName: name,

    toggle(key) {
      if (this.selected.has(key)) {
        this.selected.delete(key);
      } else {
        this.selected.add(key);
      }
      this.selected = new Set(this.selected);
    },

    has(key) {
      return this.selected.has(key);
    },

    get asArray() {
      return [...this.selected];
    },
  }));

  Alpine.data('tc6Toggle', (fieldName = 'field', initial = false) => ({
    value: initial,
    fieldName,

    toggle() {
      this.value = !this.value;
    },
  }));
});
