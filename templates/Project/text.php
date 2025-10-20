<?php
/**
 * @var \App\View\AppView $this
 * @var string|null $textContent
 * @var bool $parserAvailable
 * @var string $title
 * @var string $base
 * @var string $slug
 */
?>
<div class="content">
    <p><?= $this->Html->link('← Tilbage', ['action' => 'view', $slug]) ?></p>
    <h1><?= h($title) ?> – Tekstudtræk</h1>

    <?php if ($textContent !== null && $textContent !== ''): ?>
        <pre style="white-space: pre-wrap; background:#fafafa; border:1px solid #eee; padding:1rem;"><?= h($textContent) ?></pre>
    <?php else: ?>
        <?php if (!$parserAvailable): ?>
            <div class="notice warning">
                <p>PDF-parser er ikke installeret. For at aktivere tekstudtræk kan du installere <code>smalot/pdfparser</code>.</p>
            </div>
        <?php else: ?>
            <div class="notice">
                <p>Kunne ikke udtrække tekst fra PDF’en. Filen kan stadig læses via visningssiden.</p>
            </div>
        <?php endif; ?>
        <p><?= $this->Html->link('Åbn dokument', ['action' => 'view', $slug], ['class' => 'button']) ?></p>
    <?php endif; ?>
</div>
