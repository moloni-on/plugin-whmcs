<?php
/**
 * Layout footer: closes the module wrapper and loads the module script.
 *
 * @var callable $lang
 * @var callable $asset
 * @var callable $e
 */
?>
<div class="moloni-on__footer">
    <span><?= $e($lang('footer_note')) ?> · v1.0.0</span>
</div>
<script src="<?= $e($asset('js/app.js')) ?>"></script>
