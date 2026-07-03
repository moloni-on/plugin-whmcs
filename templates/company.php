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

            <div class="moloni-on__company-grid">
                <?php foreach ($companies as $i => $company): ?>
                    <?php
                    $logo = (string) ($company['logo'] ?? '');
                    $name = (string) ($company['name'] ?? '');
                    $initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
                    ?>
                    <label class="moloni-on__company-card">
                        <input type="radio" name="company_id" value="<?= $e($company['companyId'] ?? '') ?>"<?= $i === 0 ? ' checked' : '' ?>>
                        <span class="moloni-on__company-logo">
                            <?php if ($logo !== ''): ?>
                                <img src="<?= $e($logo) ?>" alt="<?= $e($name) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="moloni-on__company-initial"><?= $e($initial) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="moloni-on__company-body">
                            <span class="moloni-on__company-name"><?= $e($name) ?></span>
                            <?php if (!empty($company['vat'])): ?>
                                <span class="moloni-on__company-meta"><?= $e($company['vat']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($company['city'])): ?>
                                <span class="moloni-on__company-meta"><?= $e($company['city']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="moloni-on__company-check" aria-hidden="true"></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><?= $e($lang('select_company')) ?></button>
        </form>
    <?php endif; ?>
</div>
