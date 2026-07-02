<?php
/**
 * Login / connect page.
 *
 * Collects the Moloni ON developer credentials (API client id + secret) and
 * starts the OAuth2 authorization-code flow via op=connect.
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

        <div class="form-group mb-3">
            <label for="developer_id"><?= $e($lang('developer_id')) ?></label>
            <input type="text" class="form-control" id="developer_id" name="developer_id" required autocomplete="off">
        </div>

        <div class="form-group mb-3">
            <label for="client_secret"><?= $e($lang('client_secret')) ?></label>
            <input type="password" class="form-control" id="client_secret" name="client_secret" required autocomplete="off">
        </div>

        <button type="submit" class="btn btn-primary"><?= $e($lang('connect')) ?></button>
    </form>

    <p class="moloni-on__help">
        <a href="https://ac.molonion.pt/" target="_blank" rel="noopener"><?= $e($lang('login_help')) ?></a>
    </p>
</div>
