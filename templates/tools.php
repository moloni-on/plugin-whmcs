<?php
/**
 * Tools page (placeholder for future utilities).
 *
 * @var callable $lang
 * @var callable $e
 * @var array<string,mixed> $company
 */
?>
<div class="moloni-on__panel">
    <h3><?= $e($lang('tools_title')) ?></h3>

    <div class="moloni-on__tool">
        <h5><?= $e($lang('tools_connection')) ?></h5>
        <?php if (!empty($company['companyId'])): ?>
            <p class="text-success">
                <?= $e($lang('tools_connected', ['company' => (string) ($company['name'] ?? '')])) ?>
            </p>
        <?php else: ?>
            <p class="text-muted"><?= $e($lang('tools_not_connected')) ?></p>
        <?php endif; ?>
    </div>

    <div class="moloni-on__tool">
        <h5><?= $e($lang('tools_more')) ?></h5>
        <p class="text-muted"><?= $e($lang('tools_coming_soon')) ?></p>
    </div>
</div>
