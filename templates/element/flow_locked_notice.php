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
return;
