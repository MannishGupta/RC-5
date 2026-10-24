<?php
// Version: 4.4.1 — login/offline head; non-blocking fonts/FA/shell
$__rcv = rawurlencode(defined('APP_VERSION') ? (string) APP_VERSION : '1');
$__sriFa = 'sha384-iTteL4H3zXIokTJmCoRGgCFBeDfijDRGIxB8SwZjStvagwdABX2j85NZd17zrNV7';
$__sriAl = 'sha384-5/joNqFnRyVWzXp99bHot6RHG+EksGp+USSgZwPar7T9SD9PKKER37n/8bXBAZGd';
?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="/assets/fonts/rc-fonts.css?v=<?= $__rcv ?>" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="/assets/fonts/rc-fonts.css?v=<?= $__rcv ?>"></noscript>
<link rel="stylesheet" href="/assets/contrast-lock.css?v=<?= $__rcv ?>">
<link rel="stylesheet" href="/assets/rc-shell-defer.css?v=<?= $__rcv ?>" media="print" onload="this.media='all'">
<link rel="stylesheet" href="/assets/a11y.css?v=<?= $__rcv ?>" media="print" onload="this.media='all'">
<link rel="stylesheet" href="/assets/vendor/fontawesome.min.css?v=<?= $__rcv ?>" media="print" onload="this.media='all'" integrity="<?= $__sriFa ?>" crossorigin="anonymous">
<noscript>
<link rel="stylesheet" href="/assets/rc-shell-defer.css?v=<?= $__rcv ?>">
<link rel="stylesheet" href="/assets/a11y.css?v=<?= $__rcv ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome.min.css?v=<?= $__rcv ?>">
</noscript>
<script defer src="/assets/vendor/alpine.min.js?v=<?= $__rcv ?>" integrity="<?= $__sriAl ?>" crossorigin="anonymous"></script>
<?php if (!empty($__rcExtraCss) && is_string($__rcExtraCss)): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($__rcExtraCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= $__rcv ?>">
<?php endif; ?>
