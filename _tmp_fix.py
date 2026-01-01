# -*- coding: utf-8 -*-
from pathlib import Path
text = Path('templates/Flow/journey.php').read_text(encoding='utf-8', errors='replace')
start = text.find('<!-- TRIN 2c')
if start == -1:
    raise SystemExit('start not found')
end = text.find('\n\n<?php if (!empty($gateResult', start)
if end == -1:
    raise SystemExit('end not found')
card = '''<!-- TRIN 2c - Incident og Art.18/20 gating -->
<div class="card card-step">
  <div class="card-header">
    <span class="icon">⚡</span>
    <strong>TRIN 2c - Hændelse (Art.18/20 standard gating)</strong>
  </div>
  <div class="card-body">
    <p class="small muted">Brug hændelsen til at aktivere den normale art.18/20-vurdering.</p>
    <?php $main = $incident['main'] ?? ''; ?>
    <label><input type="radio" name="incident_main" value="delay" <?= $main === 'delay' ? 'checked' : '' ?> /> Forsinkelse</label><br/>
    <label><input type="radio" name="incident_main" value="cancellation" <?= $main === 'cancellation' ? 'checked' : '' ?> /> Aflysning</label><br/>
    <label><input type="checkbox" name="missed_connection" value="1" <?= ($form['missed_connection'] ?? '') === '1' ? 'checked' : '' ?> /> Mistet forbindelse</label>
    <div class="field-block" id="delay-expected-block" style="margin-top:12px; <?= $main === 'delay' ? '' : 'display:none' ?>">
      <p class="field-heading">Har du modtaget besked om ≥60 minutters forsinkelse?</p>
      <label><input type="radio" name="expected_delay_60" value="ja" <?= ($form['expected_delay_60'] ?? '') === 'ja' ? 'checked' : '' ?> /> Ja</label>
      <label><input type="radio" name="expected_delay_60" value="nej" <?= ($form['expected_delay_60'] ?? '') === 'nej' ? 'checked' : '' ?> /> Nej / ved ikke</label>
    </div>
    <div class="field-block" id="interrupted-block" style="margin-top:12px;">
      <p class="field-heading">Blev forbindelsen afbrudt og kunne ikke fortsætte (Art.20(3))?</p>
      <label><input type="radio" name="service_interrupted" value="yes" <?= ($form['service_interrupted'] ?? '') === 'yes' ? 'checked' : '' ?> /> Ja</label>
      <label><input type="radio" name="service_interrupted" value="no" <?= ($form['service_interrupted'] ?? '') === 'no' ? 'checked' : '' ?> /> Nej</label>
      <label><input type="radio" name="service_interrupted" value="unknown" <?= ($form['service_interrupted'] ?? '') === 'unknown' ? 'checked' : '' ?> /> Ved ikke</label>
    </div>
  </div>
</div>
'''
new = text[:start] + card + text[end:]
Path('templates/Flow/journey.php').write_text(new, encoding='utf-8')
