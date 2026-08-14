<?php
/**
 * Minimal full-bleed landing layout for train marketing variants.
 */

use App\View\PageContentTranslator;
use Cake\Core\Configure;

$cakeDescription = 'CakePHP: the rapid development php framework';
$publicSite = (array)Configure::read('PublicSite');
$siteContext = (array)$this->getRequest()->getAttribute('siteContext', []);
$publicSiteEnabled = array_key_exists('enabled', $siteContext)
    ? !empty($siteContext['enabled'])
    : !empty($publicSite['enabled']);
$currentPath = '/' . ltrim((string)$this->getRequest()->getUri()->getPath(), '/');
$loadDisplayFonts = str_starts_with($currentPath, '/tog');
?>
<!DOCTYPE html>
<html lang="<?= h((string)($htmlLang ?? 'da')) ?>">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= $cakeDescription ?>:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake', 'flow_stepper']) ?>
    <?php if ($loadDisplayFonts): ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
    <?php endif; ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            background: #ffffff;
        }

        body.landing-fullbleed {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body class="landing-fullbleed<?= $publicSiteEnabled ? ' is-public-site' : '' ?>">
    <?php
    $pageTranslations = isset($pageTranslations) && is_array($pageTranslations) ? $pageTranslations : [];
    $translatedContent = $this->fetch('content');
    if ($pageTranslations !== []) {
        $translatedContent = PageContentTranslator::translate($translatedContent, $pageTranslations);
    }
    ?>
    <?= $this->Flash->render() ?>
    <?= $translatedContent ?>
</body>
</html>
