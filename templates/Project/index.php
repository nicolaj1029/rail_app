<?php
/**
 * @var \App\View\AppView $this
 * @var array<int,array{slug:string,title:string}> $items
 */
?>
<div class="content">
    <h1>Projektmateriale</h1>
    <p>Vælg et dokument nedenfor. Hvis filen mangler i webroot, får du en besked om hvor den skal placeres.</p>
    <ul>
        <?php foreach ($items as $item): ?>
            <li>
                <?= $this->Html->link(h($item['title']), ['action' => 'view', $item['slug']]) ?>
                &nbsp;·&nbsp;
                <?= $this->Html->link('Tekst', ['action' => 'text', $item['slug']]) ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p>
        <?= $this->Html->link('Udfyld krav (demo)', ['controller' => 'Claims', 'action' => 'start'], ['class' => 'button']) ?>
        &nbsp;
        <?= $this->Html->link('Reimbursement form (demo)', ['controller' => 'Reimbursement', 'action' => 'start']) ?>
        &nbsp;
        <?= $this->Html->link('Start din sag (udbetaling nu)', ['controller' => 'ClientClaims', 'action' => 'start'], ['class' => 'button']) ?>
    </p>
</div>
