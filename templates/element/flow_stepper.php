<?php
/**
 * @var array<int, array<string,mixed>> $flowSteps
 * @var string $flowCurrentAction
 */
$flowSteps = $flowSteps ?? [];
$flowCurrentAction = (string)($flowCurrentAction ?? '');
$iconOf = static function (string $state): string {
    return match ($state) {
        // Use unicode escapes to avoid mojibake when files are saved as ANSI/CP1252.
        'completed' => "\u{2713}",               // ?
        'current', 'current_done' => "\u{25B6}", // ?
        'locked' => "\u{1F512}",                 // ??
        default => "\u{26A0}",                  // ?
    };
};

$classOf = static function (string $state): string {
    return match ($state) {
        'completed' => 'flow-step-completed',
        'current', 'current_done' => 'flow-step-current',
        'locked' => 'flow-step-locked',
        default => 'flow-step-needs',
    };
};
?>

<aside class="flow-stepper" aria-label="Trin navigation">
    <div class="flow-stepper__hdr">
        <div class="flow-stepper__title">Flow</div>
        <div class="flow-stepper__sub">Laaste trin kan forhaandsvises</div>
    </div>

    <ol class="flow-stepper__list">
        <?php foreach ($flowSteps as $s): ?>
            <?php
                $action = (string)($s['action'] ?? '');
                $num = (int)($s['num'] ?? 0);
                $title = (string)($s['title'] ?? '');
                $state = (string)($s['state'] ?? '');
                $visible = !array_key_exists('visible', $s) || (bool)$s['visible'];
                if (!$visible) {
                    continue;
                }
                $isLocked = $state === 'locked';
                $qs = $isLocked ? ['preview' => '1'] : [];
                $url = $this->Url->build(['controller' => 'Flow', 'action' => $action, '?' => $qs]);
            ?>
            <li class="flow-stepper__item">
                <a
                    class="flow-stepper__link <?= h($classOf($state)) ?>"
                    href="<?= h($url) ?>"
                    <?= $action === $flowCurrentAction ? 'aria-current="step"' : '' ?>
                >
                    <span class="flow-stepper__dot" aria-hidden="true"><?= h($iconOf($state)) ?></span>
                    <span class="flow-stepper__txt">
                        <span class="flow-stepper__name">Trin <?= h((string)$num) ?>: <?= h($title) ?></span>
                        <?php if ($isLocked && !empty($s['missing']) && is_array($s['missing'])): ?>
                            <span class="flow-stepper__meta">Mangler: <?= h(implode(', ', array_map('strval', $s['missing']))) ?></span>
                        <?php elseif ($state === 'completed'): ?>
                            <span class="flow-stepper__meta">Udfyldt</span>
                        <?php elseif ($state === 'needs'): ?>
                            <span class="flow-stepper__meta">Mangler input</span>
                        <?php else: ?>
                            <span class="flow-stepper__meta">&nbsp;</span>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ol>
</aside>
