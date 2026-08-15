<?php
use Cake\Core\Configure;
use App\View\PageContentTranslator;

$siteContext = (array)$this->getRequest()->getAttribute('siteContext', []);
$flags = isset($flags) && is_array($flags) ? $flags : [];
$meta = isset($meta) && is_array($meta) ? $meta : [];
$form = isset($form) && is_array($form) ? $form : [];
$transportModeClass = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? '')));
$travelStateClass = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$flowActionClass = isset($flowCurrentAction) ? strtolower((string)$flowCurrentAction) : '';
$bodyClasses = ['tc6-flow-shell'];
if (!empty($flowPreview)) {
    $bodyClasses[] = 'flow-preview';
}
if ($transportModeClass !== '') {
    $bodyClasses[] = 'flow-mode-' . preg_replace('/[^a-z0-9_-]+/', '-', $transportModeClass);
}
if ($travelStateClass !== '') {
    $bodyClasses[] = 'flow-state-' . preg_replace('/[^a-z0-9_-]+/', '-', $travelStateClass);
}
if ($flowActionClass !== '') {
    $bodyClasses[] = 'flow-action-' . preg_replace('/[^a-z0-9_-]+/', '-', $flowActionClass);
    if (in_array($flowActionClass, ['raillivestation', 'raillivetrack'], true)) {
        $bodyClasses[] = 'flow-action-railstranding';
    }
}
$bodyClass = trim(implode(' ', array_filter($bodyClasses)));
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$htmlLanguage = in_array($uiLanguage, ['da', 'en', 'fr'], true) ? $uiLanguage : 'da';
$pageTranslations = isset($pageTranslations) && is_array($pageTranslations) ? $pageTranslations : [];
$translatedContent = $this->fetch('content');
if ($pageTranslations !== []) {
    $translatedContent = PageContentTranslator::translate($translatedContent, $pageTranslations);
}
$appName = (string)(Configure::read('App.name') ?: 'TrainClaim');
$tc6TokensPath = WWW_ROOT . 'css' . DS . 'tc6' . DS . 'tokens.css';
$tc6LayoutPath = WWW_ROOT . 'css' . DS . 'tc6' . DS . 'layout.css';
$tc6ComponentsPath = WWW_ROOT . 'css' . DS . 'tc6' . DS . 'components.css';
$tc6RevealPath = WWW_ROOT . 'js' . DS . 'tc6' . DS . 'reveal.js';
$tc6FormsPath = WWW_ROOT . 'js' . DS . 'tc6' . DS . 'forms.js';
$tc6AirProgressivePath = WWW_ROOT . 'js' . DS . 'tc6' . DS . 'air-progressive.js';
$tc6StationsPath = WWW_ROOT . 'js' . DS . 'tc6' . DS . 'stations.js';
$tc6TokensVersion = is_file($tc6TokensPath) ? filemtime($tc6TokensPath) : time();
$tc6LayoutVersion = is_file($tc6LayoutPath) ? filemtime($tc6LayoutPath) : time();
$tc6ComponentsVersion = is_file($tc6ComponentsPath) ? filemtime($tc6ComponentsPath) : time();
$tc6RevealVersion = is_file($tc6RevealPath) ? filemtime($tc6RevealPath) : time();
$tc6FormsVersion = is_file($tc6FormsPath) ? filemtime($tc6FormsPath) : time();
$tc6AirProgressiveVersion = is_file($tc6AirProgressivePath) ? filemtime($tc6AirProgressivePath) : time();
$tc6StationsVersion = is_file($tc6StationsPath) ? filemtime($tc6StationsPath) : time();
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLanguage) ?>">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($appName) ?>: <?= $this->fetch('title') ?></title>
    <?= $this->Html->meta('icon') ?>
    <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake', 'flow_stepper']) ?>
    <link rel="stylesheet" href="<?= h($this->Url->build('/css/tc6/tokens.css?v=' . $tc6TokensVersion)) ?>">
    <link rel="stylesheet" href="<?= h($this->Url->build('/css/tc6/layout.css?v=' . $tc6LayoutVersion)) ?>">
    <link rel="stylesheet" href="<?= h($this->Url->build('/css/tc6/components.css?v=' . $tc6ComponentsVersion)) ?>">
    <script src="<?= h($this->Url->build('/js/tc6/reveal.js?v=' . $tc6RevealVersion)) ?>"></script>
    <script src="<?= h($this->Url->build('/js/tc6/forms.js?v=' . $tc6FormsVersion)) ?>"></script>
    <script src="<?= h($this->Url->build('/js/tc6/air-progressive.js?v=' . $tc6AirProgressiveVersion)) ?>"></script>
    <script src="<?= h($this->Url->build('/js/tc6/stations.js?v=' . $tc6StationsVersion)) ?>"></script>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
    <style>
      :root {
        --tc6-shell-bg: #f3f5fb;
        --tc6-shell-panel: #ffffff;
        --tc6-shell-border: rgba(15, 23, 42, 0.08);
        --tc6-shell-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        --tc6-shell-text: #0f172a;
        --tc6-shell-muted: #64748b;
      }

      body.tc6-flow-shell {
        margin: 0;
        min-height: 100vh;
        background: #f4f7fb;
        color: var(--tc6-shell-text);
      }

      .tc6-flow-stage {
        max-width: 1320px;
        margin: 0 auto;
        padding: 8px 20px 48px;
      }

      .tc6-flow-panel {
        background: transparent;
        border: 0;
        border-radius: 0;
        box-shadow: none;
        backdrop-filter: none;
        padding: 24px 0;
      }

      .tc6-flow-flash {
        margin: 0 0 18px;
      }

      .tc6-flow-panel .container {
        width: 100%;
        max-width: none;
        padding: 0;
      }

      .tc6-flow-panel .flow-layout {
        align-items: start;
      }

      .tc6-flow-panel .flow-content > *:first-child {
        margin-top: 0;
      }

      body.tc6-flow-shell .flow-content {
        max-width: 840px;
      }

      body.tc6-flow-shell .flow-content h1 {
        margin: 0 0 10px;
        font-family: "Raleway", "Segoe UI", sans-serif;
        font-size: clamp(2.2rem, 4vw, 3.6rem);
        line-height: 1.04;
        letter-spacing: -0.035em;
        color: #0f172a;
      }

      body.tc6-flow-shell .flow-content h2,
      body.tc6-flow-shell .flow-content h3,
      body.tc6-flow-shell .flow-content legend,
      body.tc6-flow-shell .flow-content strong {
        color: #0f172a;
      }

      body.tc6-flow-shell .flow-content p,
      body.tc6-flow-shell .flow-content li,
      body.tc6-flow-shell .flow-content label,
      body.tc6-flow-shell .flow-content .small,
      body.tc6-flow-shell .flow-content .muted {
        color: #475569;
      }

      body.tc6-flow-shell .flow-content fieldset {
        border: 0;
        padding: 0;
        margin: 0;
        min-width: 0;
      }

      body.tc6-flow-shell .flow-content input[type="text"],
      body.tc6-flow-shell .flow-content input[type="email"],
      body.tc6-flow-shell .flow-content input[type="number"],
      body.tc6-flow-shell .flow-content input[type="date"],
      body.tc6-flow-shell .flow-content input[type="time"],
      body.tc6-flow-shell .flow-content input[type="tel"],
      body.tc6-flow-shell .flow-content input[type="search"],
      body.tc6-flow-shell .flow-content input[type="file"],
      body.tc6-flow-shell .flow-content select,
      body.tc6-flow-shell .flow-content textarea {
        width: 100%;
        min-height: 48px;
        border-radius: 14px;
        border: 1px solid #d7deea;
        background: #fff;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
        color: #0f172a;
        padding: 11px 14px;
        font-size: 1.45rem;
      }

      body.tc6-flow-shell .flow-content textarea {
        min-height: 110px;
        resize: vertical;
      }

      body.tc6-flow-shell .flow-content input:focus,
      body.tc6-flow-shell .flow-content select:focus,
      body.tc6-flow-shell .flow-content textarea:focus {
        outline: none;
        border-color: #7db2ff;
        box-shadow: 0 0 0 4px rgba(29, 111, 216, 0.12);
      }

      body.tc6-flow-shell .flow-content .card,
      body.tc6-flow-shell .flow-content .panel,
      body.tc6-flow-shell .flow-content .hl {
        border: 1px solid #dbe4ee !important;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.98)) !important;
        border-radius: 18px !important;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        padding: 18px 20px !important;
      }

      body.tc6-flow-shell .flow-content .card + .card,
      body.tc6-flow-shell .flow-content .panel + .panel {
        margin-top: 16px;
      }

      body.tc6-flow-shell .flow-content .card-title,
      body.tc6-flow-shell .flow-content .widget-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.7rem;
        font-weight: 700;
        color: #0f172a;
      }

      body.tc6-flow-shell .flow-content .icon,
      body.tc6-flow-shell .flow-content .step-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: #e8f1ff;
        color: #1d6fd8;
        font-size: 1.2rem;
        flex: 0 0 auto;
      }

      body.tc6-flow-shell .flow-content .grid-2,
      body.tc6-flow-shell .flow-content .grid-3 {
        gap: 14px;
      }

      body.tc6-flow-shell .flow-content .button,
      body.tc6-flow-shell .flow-content .btn,
      body.tc6-flow-shell .flow-content button {
        min-height: 48px;
        border-radius: 14px;
        border: 1px solid transparent;
        box-shadow: none;
        font-weight: 600;
        letter-spacing: -0.01em;
        padding: 0 18px;
      }

      body.tc6-flow-shell .flow-content button,
      body.tc6-flow-shell .flow-content .button.button-primary,
      body.tc6-flow-shell .flow-content .button[type="submit"] {
        background: #0f172a;
        color: #fff;
      }

      body.tc6-flow-shell .flow-content a.button,
      body.tc6-flow-shell .flow-content .button.button-outline,
      body.tc6-flow-shell .flow-content .btn {
        background: #fff !important;
        color: #0f172a !important;
        border-color: #d7deea !important;
      }

      body.tc6-flow-shell .flow-content button:hover,
      body.tc6-flow-shell .flow-content .button:hover,
      body.tc6-flow-shell .flow-content .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 18px rgba(15, 23, 42, 0.08);
      }

      body.tc6-flow-shell .flow-content input[type="radio"],
      body.tc6-flow-shell .flow-content input[type="checkbox"] {
        width: 18px;
        height: 18px;
        min-height: 18px;
        margin-right: 8px;
        accent-color: #1d6fd8;
      }

      body.tc6-flow-shell .flow-content [data-show-if].card {
        margin-top: 14px;
      }

      body.tc6-flow-shell .flow-content .ok {
        background: #edfdf3;
        color: #166534;
        border-radius: 12px;
        padding: 10px 12px;
      }

      body.tc6-flow-shell .flow-content .small.muted,
      body.tc6-flow-shell .flow-content .muted {
        color: #64748b !important;
      }

      body.tc6-flow-shell .flow-content > form > div:last-child,
      body.tc6-flow-shell .flow-content > .flow-wrapper > form > div:last-child {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
        margin-top: 22px;
      }

      @media (max-width: 980px) {
        .tc6-flow-stage {
          padding-left: 14px;
          padding-right: 14px;
        }

        .tc6-flow-panel {
          padding: 16px;
          border-radius: 20px;
        }

        body.tc6-flow-shell .flow-content {
          max-width: none;
        }
      }
    </style>
</head>
<body<?= $bodyClass !== '' ? ' class="' . h($bodyClass) . '"' : '' ?>>
    <main class="tc6-flow-stage">
        <div class="tc6-flow-panel">
            <div class="tc6-flow-flash">
                <?= $this->Flash->render() ?>
            </div>
            <?= $translatedContent ?>
        </div>
    </main>
</body>
</html>
