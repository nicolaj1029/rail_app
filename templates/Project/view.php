<?php
/**
 * @var \App\View\AppView $this
 * @var array{fsPath:string,webPath:string}|null $fileInfo
 * @var string $slug
 * @var string $base
 */
?>
<div class="content">
    <p><?= $this->Html->link('← Tilbage', ['action' => 'index']) ?></p>
    <h1><?= h($title ?? 'Dokument') ?></h1>

    <?php if (!$fileInfo): ?>
        <div class="notice warning">
            <p>Filen for "<?= h($base) ?>" blev ikke fundet i webroot.</p>
            <p>Tilføj en fil i mappen <code>webroot/</code> (evt. under <code>webroot/files/</code> eller <code>webroot/docs/</code>) med et af følgende navne:</p>
            <ul>
                <li><code><?= h($base) ?>.pdf</code></li>
                <li><code><?= h($base) ?>.html</code> eller <code><?= h($base) ?>.htm</code></li>
                <li>Et billede: <code><?= h($base) ?>.png</code>, <code>.jpg</code>, <code>.svg</code> mv.</li>
            </ul>
            <p>Opdater siden efter upload.</p>
        </div>
    <?php else: ?>
        <?php
        $ext = strtolower(pathinfo($fileInfo['webPath'], PATHINFO_EXTENSION));
        $embeds = ['pdf','html','htm'];
        ?>
        <?php if (in_array($ext, $embeds, true)): ?>
            <?php if ($ext === 'pdf'): ?>
                <object data="<?= h($fileInfo['webPath']) ?>#view=fit" type="application/pdf" width="100%" height="800">
                    <p>Din browser kan ikke vise PDF. Du kan <a href="<?= h($fileInfo['webPath']) ?>" download>downloade filen</a>.</p>
                </object>
            <?php else: ?>
                <iframe src="<?= h($fileInfo['webPath']) ?>" width="100%" height="800" style="border:1px solid #ddd"></iframe>
            <?php endif; ?>
        <?php elseif (in_array($ext, ['png','jpg','jpeg','gif','webp','svg'], true)): ?>
            <figure>
                <img src="<?= h($fileInfo['webPath']) ?>" alt="<?= h($base) ?>" style="max-width:100%;height:auto" />
            </figure>
        <?php else: ?>
            <p>Filtypen (.<code><?= h($ext) ?></code>) vises ikke direkte her. Du kan hente den nedenfor.</p>
        <?php endif; ?>

        <p>
            <?= $this->Html->link('Download', $fileInfo['webPath'], ['download' => true, 'class' => 'button']) ?>
            &nbsp;
            <?= $this->Html->link('Åbn i ny fane', $fileInfo['webPath'], ['target' => '_blank', 'rel' => 'noopener']) ?>
        </p>
    <?php endif; ?>
</div>
