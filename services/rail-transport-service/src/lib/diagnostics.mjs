export function createDiagnostics() {
  return {
    providers: [],
    warnings: [],
    errors: []
  };
}

export function pushProviderDiagnostic(diagnostics, payload) {
  diagnostics.providers.push({
    provider: payload.provider,
    ok: Boolean(payload.ok),
    item_count: Number.isFinite(payload.itemCount) ? payload.itemCount : 0,
    elapsed_ms: Number.isFinite(payload.elapsedMs) ? payload.elapsedMs : 0,
    error: payload.error || null
  });
}

export function pushWarning(diagnostics, warning) {
  if (!warning) return;
  diagnostics.warnings.push(String(warning));
}

export function pushError(diagnostics, error) {
  if (!error) return;
  diagnostics.errors.push(String(error));
}
