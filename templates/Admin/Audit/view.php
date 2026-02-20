<?php /** @var \App\View\AppView $this */ ?>
<?php if (!empty($error)): ?>
  <h1>Audit</h1>
  <div class="card" style="padding:12px;border:1px solid #ddd;background:#fff;border-radius:6px;">
    <strong>Fejl</strong>
    <div class="small mt8"><?= h((string)$error) ?></div>
    <?php if (!empty($file)): ?><div class="small mt4">file: <code><?= h((string)$file) ?></code></div><?php endif; ?>
    <?php if (!empty($path)): ?><div class="small mt4">path: <code><?= h((string)$path) ?></code></div><?php endif; ?>
    <div class="mt12"><a class="button" style="background:#eee;color:#333;" href="<?= $this->Url->build(['action'=>'index']) ?>">Tilbage</a></div>
  </div>
  <?php return; ?>
<?php endif; ?>

<style>
  .audit-wrap { display:grid; grid-template-columns: 280px 1fr; gap:12px; }
  .audit-card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .audit-toc a { display:block; text-decoration:none; padding:4px 0; color:#004085; }
  .audit-main h1, .audit-main h2, .audit-main h3 { margin-top:18px; }
  .audit-main pre { background:#0b1020; color:#e6edf3; padding:10px; border-radius:6px; overflow:auto; font-size:12px; }
  .audit-main code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  .audit-main ul { margin: 6px 0 12px 20px; }
  @media (max-width: 900px) {
    .audit-wrap { grid-template-columns: 1fr; }
  }
</style>

<h1>Audit rapport</h1>

<?php
  $m = (array)($meta ?? []);
  $md = (string)($contentMd ?? '');
  $heads = [];
  foreach (preg_split("/\\r?\\n/", $md) as $ln) {
    if (preg_match('/^(#{1,3})\\s+(.*)$/', (string)$ln, $mm)) {
      $lvl = strlen((string)$mm[1]);
      $txt = trim((string)$mm[2]);
      $id = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $txt) ?? '');
      $id = trim($id, '-');
      if ($id === '') { $id = 'h' . $lvl . '_' . substr(sha1($txt), 0, 8); }
      $heads[] = ['lvl'=>$lvl,'txt'=>$txt,'id'=>$id];
    }
  }
?>

<div class="audit-wrap">
  <div class="audit-card">
    <div class="small"><strong>Metadata</strong></div>
    <div class="small mt4">Fil: <code><?= h((string)($m['file'] ?? '')) ?></code></div>
    <div class="small mt4">Tid: <code><?= !empty($m['mtime']) ? h(date('Y-m-d H:i:s', (int)$m['mtime'])) : '-' ?></code></div>
    <div class="small mt4">Størrelse: <code><?= !empty($m['size']) ? h(number_format(((int)$m['size'])/1024, 1)) . ' KB' : '-' ?></code></div>

    <div class="mt12">
      <a class="button" style="background:#eee;color:#333;" href="<?= $this->Url->build(['action'=>'index']) ?>">Tilbage</a>
      <a class="button" style="margin-top:8px;display:inline-block;background:#004085;color:#fff;" href="<?= $this->Url->build(['action'=>'latest']) ?>">Seneste</a>
    </div>

    <hr/>
    <div class="small"><strong>Indhold</strong></div>
    <div class="audit-toc small mt8">
      <?php if (empty($heads)): ?>
        <div class="muted">Ingen overskrifter fundet.</div>
      <?php else: ?>
        <?php foreach ($heads as $h): ?>
          <?php $pad = max(0, ((int)$h['lvl'] - 1)) * 10; ?>
          <a style="padding-left:<?= (int)$pad ?>px" href="#<?= h((string)$h['id']) ?>"><?= h((string)$h['txt']) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="audit-card audit-main">
    <?= $contentHtml ?? '' ?>
  </div>
</div>

