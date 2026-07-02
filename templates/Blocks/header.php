<?php
/**
 * Layout header. WHMCS already provides <html>/<head>; this opens the module
 * wrapper, loads our stylesheet and shows the module title bar.
 *
 * @var callable $lang
 * @var callable $asset
 * @var callable $e
 * @var array<string,mixed> $company
 */
$companyName = $company['name'] ?? '';
?>
<link rel="stylesheet" href="<?= $e($asset('css/style.css')) ?>">
<div class="moloni-on">
    <div class="moloni-on__topbar">
        <div class="moloni-on__brand">
            <img src="<?= $e($asset('img/logo.png')) ?>" alt="Moloni ON" onerror="this.style.display='none'">
            <span><?= $e($lang('module_name')) ?></span>
        </div>
        <?php if ($companyName !== ''): ?>
            <div class="moloni-on__company">
                <?= $e($lang('current_company')) ?>: <strong><?= $e($companyName) ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>
