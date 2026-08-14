<?php
/** @var \App\View\AppView $this */

$railNav = $railNav ?? [];
$brandHref = (string)($brandHref ?? '/tog');
$brandLabel = (string)($brandLabel ?? 'TrainClaim');
$landingAnchors = (bool)($landingAnchors ?? false);
$fullBleedNav = (bool)($fullBleedNav ?? false);
$navTheme = (string)($navTheme ?? 'light');
$languageLinks = (array)($languageLinks ?? []);
$uiText = (array)($uiText ?? []);
$currentPath = '/' . ltrim((string)$this->getRequest()->getUri()->getPath(), '/');
?>
<style>
  .tc-nav-wrap {
    max-width: 1120px;
    margin: 0 auto;
    padding: 18px 16px 10px;
  }

  .tc-nav-wrap.tc-nav-wrap--fullbleed {
    max-width: none;
    padding: 0 24px;
  }

  .tc-nav-wrap.tc-nav-wrap--fullbleed.is-dark {
    background: #0f1b2d;
  }

  .tc-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
  }

  .tc-nav-wrap.tc-nav-wrap--fullbleed .tc-nav {
    max-width: 1200px;
    margin: 0 auto;
    min-height: 84px;
  }

  .tc-brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #1f2937;
    text-decoration: none;
    font-family: 'Syne', 'Segoe UI', sans-serif;
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.02em;
  }

  .tc-nav-wrap.is-dark .tc-brand {
    color: #f8fafc;
  }

  .tc-brand-mark {
    width: 62px;
    height: 62px;
    border-radius: 999px;
    object-fit: contain;
    object-position: center;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
    flex: 0 0 auto;
    background: transparent;
  }

  .tc-nav-links {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .tc-nav-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .tc-nav-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 12px;
    color: #6b7280;
    text-decoration: none;
    font-weight: 500;
    transition: background .18s ease, color .18s ease;
  }

  .tc-nav-wrap.is-dark .tc-nav-link {
    color: rgba(226, 232, 240, 0.74);
  }

  .tc-nav-link:hover,
  .tc-nav-link.active {
    color: #0f172a;
    background: rgba(29, 111, 216, 0.07);
  }

  .tc-nav-wrap.is-dark .tc-nav-link:hover,
  .tc-nav-wrap.is-dark .tc-nav-link.active {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
  }

  .tc-nav-cta {
    background: #1d6fd8;
    color: #fff;
  }

  .tc-nav-cta:hover {
    color: #fff;
    background: #175db8;
  }

  .tc-lang-switch {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-left: 8px;
  }

  .tc-lang-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .tc-nav-wrap.is-dark .tc-lang-label {
    color: rgba(226, 232, 240, 0.76);
  }

  .tc-lang-select {
    min-height: 38px;
    min-width: 170px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.34);
    background: #fff;
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
  }

  .tc-nav-wrap.is-dark .tc-lang-select {
    background: rgba(15, 23, 42, 0.34);
    color: #f8fafc;
    border-color: rgba(255, 255, 255, 0.14);
  }

  @media (max-width: 720px) {
    .tc-nav {
      align-items: flex-start;
    }

    .tc-nav-wrap.tc-nav-wrap--fullbleed {
      padding-left: 16px;
      padding-right: 16px;
    }

    .tc-lang-switch {
      width: 100%;
      margin-left: 0;
    }

    .tc-lang-select {
      width: 100%;
    }
  }
</style>

<div class="tc-nav-wrap<?= $fullBleedNav ? ' tc-nav-wrap--fullbleed' : '' ?><?= $navTheme === 'dark' ? ' is-dark' : '' ?>">
  <header class="tc-nav">
    <a class="tc-brand" href="<?= h($this->Url->build($brandHref)) ?>">
      <img class="tc-brand-mark" src="<?= h($this->Url->image('raven-emblem.png')) ?>" alt="" aria-hidden="true">
      <span><?= h($brandLabel) ?></span>
    </a>

    <div class="tc-nav-meta">
      <nav class="tc-nav-links" aria-label="Landing navigation">
        <?php foreach ((array)$railNav as $item): ?>
          <?php $label = (string)($item['label'] ?? 'Link'); ?>
          <?php $href = (string)($item['href'] ?? '#'); ?>
          <?php if (in_array($label, ['Live rejse', 'Start krav'], true)) { continue; } ?>
          <?php $isCta = in_array($label, ['Live rejse', 'Start krav'], true); ?>
          <a class="tc-nav-link<?= !empty($item['active']) ? ' active' : '' ?><?= $isCta && $label === 'Start krav' ? ' tc-nav-cta' : '' ?>" href="<?= h($href) ?>">
            <?= h($label) ?>
          </a>
        <?php endforeach; ?>
        <?php if ($landingAnchors): ?>
          <a class="tc-nav-link" href="#faq"><?= h((string)($uiText['nav.faq'] ?? 'FAQ')) ?></a>
          <a class="tc-nav-link" href="#om-os"><?= h((string)($uiText['nav.about'] ?? 'Om os')) ?></a>
          <a class="tc-nav-link" href="#kontakt"><?= h((string)($uiText['nav.contact'] ?? 'Kontakt')) ?></a>
        <?php endif; ?>
      </nav>
      <?php if ($languageLinks !== []): ?>
        <div class="tc-lang-switch" aria-label="Language switch">
          <span class="tc-lang-label"><?= h((string)($uiText['ui.language'] ?? 'Sprog')) ?></span>
          <select class="tc-lang-select" onchange="if (this.value) { window.location.href = this.value; }">
            <?php foreach ($languageLinks as $item): ?>
              <option value="<?= h((string)($item['href'] ?? '#')) ?>" <?= !empty($item['active']) ? 'selected' : '' ?>>
                <?= h((string)($item['label'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </div>
  </header>
</div>
