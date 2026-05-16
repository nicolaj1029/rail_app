<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 */

use Cake\Core\Configure;

$cakeDescription = 'CakePHP: the rapid development php framework';
$publicSite = (array)Configure::read('PublicSite');
$siteContext = (array)$this->getRequest()->getAttribute('siteContext', []);
$publicSiteEnabled = array_key_exists('enabled', $siteContext)
    ? !empty($siteContext['enabled'])
    : !empty($publicSite['enabled']);
$hideTopNav = $publicSiteEnabled
    && (array_key_exists('hideTopNav', $siteContext) ? !empty($siteContext['hideTopNav']) : !empty($publicSite['hideTopNav']));
$publicLandingPath = '/' . ltrim((string)($siteContext['landingPath'] ?? ($publicSite['landingPath'] ?? '/passenger/start')), '/');
$currentPath = '/' . ltrim((string)$this->getRequest()->getUri()->getPath(), '/');
$showPublicBackLink = $publicSiteEnabled
    && $currentPath !== rtrim($publicLandingPath, '/')
    && str_starts_with($currentPath, '/flow');
?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= $cakeDescription ?>:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake', 'flow_stepper']) ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<?php $bodyClass = !empty($flowPreview) ? 'flow-preview' : ''; ?>
<body<?= $bodyClass !== '' ? ' class="' . h($bodyClass) . '"' : '' ?>>
    <?php if (!$hideTopNav): ?>
        <nav class="top-nav">
            <div class="top-nav-title">
                <a href="<?= $this->Url->build('/') ?>"><span>Cake</span>PHP</a>
            </div>
            <div class="top-nav-links">
                <a href="<?= $this->Url->build('/flow/start') ?>">Flow</a>
                <a href="<?= $this->Url->build('/flow/air/completed') ?>">Fly A</a>
                <a href="<?= $this->Url->build('/flow/air/ongoing') ?>">Fly I</a>
                <a href="<?= $this->Url->build('/flow/rail/completed') ?>">Tog A</a>
                <a href="<?= $this->Url->build('/flow/rail/ongoing') ?>">Tog I</a>
                <a href="<?= $this->Url->build('/flow/bus/completed') ?>">Bus A</a>
                <a href="<?= $this->Url->build('/flow/bus/ongoing') ?>">Bus I</a>
                <a href="<?= $this->Url->build('/flow/ferry/completed') ?>">Færge A</a>
                <a href="<?= $this->Url->build('/flow/ferry/ongoing') ?>">Færge I</a>
                <a href="<?= $this->Url->build('/passenger/start') ?>">Passager</a>
                <a href="<?= $this->Url->build('/project/flow-qa') ?>">Flow QA</a>
                <a href="<?= $this->Url->build('/project/chat-qa') ?>">Chat QA</a>
                <a href="<?= $this->Url->build('/admin/desk') ?>">Admin Desk</a>
                <a href="<?= $this->Url->build('/admin/chat') ?>">Admin Chat</a>
                <a href="<?= $this->Url->build('/admin/audit/latest') ?>">Audit</a>
                <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/">Docs</a>
                <a target="_blank" rel="noopener" href="https://api.cakephp.org/">API</a>
            </div>
        </nav>
    <?php endif; ?>
    <main class="main">
        <div class="container">
            <?= $this->Flash->render() ?>
            <?php if ($showPublicBackLink): ?>
                <div style="display:flex; justify-content:flex-end; margin:14px 0 10px;">
                    <a href="<?= h($this->Url->build($publicLandingPath)) ?>" style="display:inline-block; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; background:#fff; color:#0f172a; text-decoration:none; font-weight:600;">Aabn hovedmenu</a>
                </div>
            <?php endif; ?>
            <?php if (!empty($flowSteps) && is_array($flowSteps)): ?>
                <div class="flow-layout">
                    <?= $this->element('flow_stepper', ['flowSteps' => $flowSteps, 'flowCurrentAction' => $flowCurrentAction ?? '']) ?>
                    <div class="flow-content">
                        <?= $this->fetch('content') ?>
                    </div>
                </div>
            <?php else: ?>
                <?= $this->fetch('content') ?>
            <?php endif; ?>
        </div>
    </main>
    <footer>
    </footer>
</body>
</html>
