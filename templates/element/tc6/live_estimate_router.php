<?php
/**
 * Small router element that renders the correct live estimate for tc6 side panels.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);

$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['gating_mode'] ?? ($meta['transport_mode'] ?? ($multimodal['transport_mode'] ?? '')))));

if ($transportMode === 'air') {
    $airRights = (array)($multimodal['air_rights'] ?? []);
    $airScope = (array)($multimodal['air_scope'] ?? []);
    $airContract = (array)($multimodal['air_contract'] ?? []);
    echo $this->element('air_live_estimate', compact('form', 'flags', 'meta', 'airRights', 'airScope', 'airContract'));
    return;
}

if ($transportMode === 'ferry') {
    $ferryRights = (array)($multimodal['ferry_rights'] ?? []);
    $ferryScope = (array)($multimodal['ferry_scope'] ?? []);
    echo $this->element('ferry_live_estimate', compact('form', 'flags', 'meta', 'journey', 'ferryRights', 'ferryScope'));
    return;
}

if ($transportMode === 'rail' || $transportMode === '') {
    echo $this->element('rail_live_estimate', compact('form', 'flags', 'meta', 'journey'));
}
