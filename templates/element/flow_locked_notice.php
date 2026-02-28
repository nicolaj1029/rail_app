<?php
/**
 * Shared notice for read-only preview mode when a step is locked by prerequisites.
 *
 * Expected vars:
 * - $flowPreview (bool)
 * - $flowMissingPrereqs (array)
 */
$flowPreview = !empty($flowPreview);
$missing = $flowMissingPrereqs ?? [];
if (!$flowPreview) { return; }
?>

<div class="flow-locked-notice" role="status">
    <strong>Dette trin er laast (forhaandsvisning).</strong>
    <div style="margin-top:.4rem;">
        Udfyld forrige trin for at redigere.
        <?php if (!empty($missing) && is_array($missing)): ?>
            <div style="margin-top:.4rem; opacity:.85;">
                Mangler: <?= h(implode(', ', array_map('strval', $missing))) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

