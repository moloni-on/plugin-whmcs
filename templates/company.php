<?php
/**
 * Company selection page.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var callable $csrf
 * @var array<int,array<string,mixed>> $companies
 */
?>
<div class="moloni-on__panel moloni-on__company-select">
    <h3><?= $e($lang('company_title')) ?></h3>

    <?php if (empty($companies)): ?>
        <p class="text-muted"><?= $e($lang('company_none')) ?></p>
    <?php else: ?>
        <form method="post" action="<?= $e($url()) ?>">
            <?= $csrf() ?>
            <input type="hidden" name="op" value="selectCompany">

            <div class="moloni-on__company-list">
                <?php foreach ($companies as $i => $company): ?>
                    <label class="moloni-on__company-item">
                        <input type="radio" name="company_id" value="<?= $e($company['companyId'] ?? '') ?>"<?= $i === 0 ? ' checked' : '' ?>>
                        <span class="moloni-on__company-name"><?= $e($company['name'] ?? '') ?></span>
                        <span class="moloni-on__company-meta">
                            <?= $e($company['vat'] ?? '') ?> · <?= $e($company['city'] ?? '') ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><?= $e($lang('select_company')) ?></button>
        </form>
    <?php endif; ?>
</div>
