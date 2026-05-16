<?php
/** @var \App\View\AppView $this */
use Cake\Core\Configure;

$passengerNav = $passengerNav ?? [];
$publicSite = (array)Configure::read('PublicSite');
$siteContext = (array)$this->getRequest()->getAttribute('siteContext', []);
$publicSiteEnabled = array_key_exists('enabled', $siteContext)
    ? !empty($siteContext['enabled'])
    : !empty($publicSite['enabled']);
$hidePassengerNav = $publicSiteEnabled
    && (array_key_exists('hidePassengerNav', $siteContext) ? !empty($siteContext['hidePassengerNav']) : !empty($publicSite['hidePassengerNav']));
if ($hidePassengerNav) {
    return;
}
?>
<style>
  .passenger-shell { display:grid; grid-template-columns: minmax(0, 1fr); gap: 18px; max-width: 1400px; margin: 0 auto; padding: 0 16px 24px; }
  .passenger-sidebar { border: 1px solid #dce8df; border-radius: 24px; background: linear-gradient(135deg, #fffef9 0%, #f3f8ef 100%); padding: 18px 20px; position: sticky; top: 16px; align-self: start; box-shadow: 0 10px 24px rgba(18, 32, 42, .05); }
  .passenger-sidebar-inner { display:flex; align-items:center; justify-content:space-between; gap: 16px; flex-wrap:wrap; }
  .passenger-brand-wrap { display:flex; flex-direction:column; gap: 4px; }
  .passenger-brand { font-size: 18px; font-weight: 800; letter-spacing: .04em; color: #146c7f; text-transform: uppercase; }
  .passenger-subtitle { font-size: 13px; color: #60707a; }
  .passenger-nav { display:flex; align-items:center; gap: 10px; flex-wrap:wrap; }
  .passenger-nav-link { display:flex; align-items:center; gap: 10px; padding: 10px 14px; border-radius: 999px; color: #36505d; text-decoration:none; font-weight: 600; border: 1px solid #d6e4da; background: rgba(255,255,255,.72); }
  .passenger-nav-link:hover { background: #ffffff; color: #163947; border-color: #bfd3c4; }
  .passenger-nav-link.active { background: #163947; color: #fff; border-color: #163947; }
  .passenger-sidebar-footer { display:flex; align-items:center; gap: 10px; color: #60707a; font-size: 13px; }
  .passenger-help-dot { width: 10px; height: 10px; border-radius: 999px; background: #4aa774; flex: 0 0 auto; }
  .passenger-main { min-width: 0; }
  @media (max-width: 980px) {
    .passenger-sidebar { position: static; }
    .passenger-sidebar-inner { align-items:flex-start; }
  }
</style>
<div class="passenger-shell">
  <aside class="passenger-sidebar">
    <div class="passenger-sidebar-inner">
      <div class="passenger-brand-wrap">
        <div class="passenger-brand">FLYPENGE</div>
        <div class="passenger-subtitle">Passagerpanel for air-sager</div>
      </div>
      <nav class="passenger-nav">
        <?php foreach ((array)$passengerNav as $item): ?>
          <a class="passenger-nav-link<?= !empty($item['active']) ? ' active' : '' ?>" href="<?= h((string)($item['href'] ?? '#')) ?>">
            <span><?= h((string)($item['label'] ?? 'Link')) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="passenger-sidebar-footer">
        <span class="passenger-help-dot" aria-hidden="true"></span>
        <span>Brug chat eller review, hvis sagen mangler oplysninger.</span>
      </div>
    </div>
  </aside>
  <div class="passenger-main">
