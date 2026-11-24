<?php
/**
 * Login / connect page.
 *
 * Starts the Moloni ON OAuth2 authorization-code + PKCE flow via op=connect.
 * No credentials to enter: the module registers a public OAuth client for
 * this WHMCS installation and redirects to Moloni ON to authorize it.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var callable $csrf
 */
?>
<div class="moloni-on__panel moloni-on__login">
    <h3><?= $e($lang('login_title')) ?></h3>
    <p class="text-muted"><?= $e($lang('login_intro')) ?></p>

    <form method="post" action="<?= $e($url()) ?>">
        <?= $csrf() ?>
        <input type="hidden" name="op" value="connect">

        <button type="submit" class="btn btn-primary"><?= $e($lang('connect')) ?></button>
    </form>
</div>
