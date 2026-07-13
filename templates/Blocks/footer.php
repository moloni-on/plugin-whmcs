<?php
/**
 * Layout footer: closes the module wrapper and loads the module script.
 *
 * The help/guides URL comes from {@see \MoloniOn\Support\Platform::HELP_URL} so
 * all external endpoints live in one place.
 *
 * @var callable $lang
 * @var callable $asset
 * @var callable $e
 */
?>
<div class="moloni-on__footer">
    <span><?= $e($lang('footer_help_lead')) ?></span>
    <a href="<?= $e(\MoloniOn\Support\Platform::HELP_URL) ?>" target="_blank" rel="noopener"><?= $e($lang('footer_help_link')) ?></a>
</div>
<script src="<?= $e($asset('js/app.js')) ?>"></script>
