<?php /** @var \App\View\AppView $this */ ?>
<h1>Audit</h1>

<div class="card" style="padding:12px;border:1px solid #ddd;background:#fff;border-radius:6px;">
  <div class="small">Viser seneste audit-rapporter fra <code>LOGS/</code>. Kræver at du har kørt audit via CLI.</div>
  <div class="small mt8">
    <strong>Genveje</strong>:
    <a href="<?= $this->Url->build(['action' => 'latest', '?' => ['type' => 'regulation']]) ?>">Seneste regulation-audit</a>
    · <a href="<?= $this->Url->build(['action' => 'latest', '?' => ['type' => 'ai']]) ?>">Seneste ai-audit</a>
  </div>
</div>

<div class="mt12" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
  <a class="button" style="<?= $type==='regulation'?'background:#004085;color:#fff;':'background:#eee;color:#333;' ?>" href="<?= $this->Url->build(['action' => 'index', '?' => ['type' => 'regulation']]) ?>">Regulation</a>
  <a class="button" style="<?= $type==='ai'?'background:#004085;color:#fff;':'background:#eee;color:#333;' ?>" href="<?= $this->Url->build(['action' => 'index', '?' => ['type' => 'ai']]) ?>">AI</a>
  <a class="button" style="<?= $type==='all'?'background:#004085;color:#fff;':'background:#eee;color:#333;' ?>" href="<?= $this->Url->build(['action' => 'index', '?' => ['type' => 'all']]) ?>">Alle</a>
</div>

<div class="card mt12" style="padding:12px;border:1px solid #ddd;background:#fff;border-radius:6px;">
  <strong>Sådan kører du audits</strong>
  <pre style="background:#f6f8fa;padding:10px;border-radius:6px;overflow:auto;font-size:12px;margin-top:8px;"><?=
  h("cd C:\\\\wamp64\\\\www\\\\rail_app\n".
    "python scripts\\\\regulations\\\\index_32021r0782_da.py\n".
    "$env:GROQ_API_KEY=\\\"...\\\"  # Groq key\n".
    "php bin/cake.php regulation_audit --limit 6\n".
    "php bin/cake.php ai_audit\n")
  ?></pre>
</div>

<div class="card mt12" style="padding:12px;border:1px solid #ddd;background:#fff;border-radius:6px;">
  <strong>Rapporter (<?= h($type) ?>)</strong>
  <?php if (empty($reports)): ?>
    <div class="small muted mt8">Ingen rapporter fundet endnu.</div>
  <?php else: ?>
    <table class="mt8" style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;border-bottom:1px solid #eee;padding:6px;">Fil</th>
          <th style="text-align:left;border-bottom:1px solid #eee;padding:6px;">Type</th>
          <th style="text-align:left;border-bottom:1px solid #eee;padding:6px;">Tid</th>
          <th style="text-align:left;border-bottom:1px solid #eee;padding:6px;">Størrelse</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($reports as $r): ?>
        <?php
          $t = (string)($r['type'] ?? '');
          $f = (string)($r['file'] ?? '');
          $mt = (int)($r['mtime'] ?? 0);
          $sz = (int)($r['size'] ?? 0);
        ?>
        <tr>
          <td style="padding:6px;border-bottom:1px solid #f3f3f3;">
            <a href="<?= $this->Url->build(['action' => 'view', '?' => ['file' => $f]]) ?>"><?= h($f) ?></a>
            <?php if (!empty($latest) && (string)($latest['file'] ?? '') === $f): ?>
              <span class="badge" style="margin-left:6px;">seneste</span>
            <?php endif; ?>
          </td>
          <td style="padding:6px;border-bottom:1px solid #f3f3f3;"><?= h($t) ?></td>
          <td style="padding:6px;border-bottom:1px solid #f3f3f3;"><?= $mt>0 ? h(date('Y-m-d H:i:s', $mt)) : '-' ?></td>
          <td style="padding:6px;border-bottom:1px solid #f3f3f3;"><?= h(number_format(max(0,$sz)/1024, 1)) ?> KB</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
