<?php
/**
 * Primary navigation tabs.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var callable $postForm
 * @var string $activeTab
 */
$tabs = [
    'orders' => ['label' => $lang('nav_orders'), 'params' => []],
    'documents' => ['label' => $lang('nav_documents'), 'params' => ['action' => 'documents']],
    'discarded' => ['label' => $lang('nav_discarded'), 'params' => ['action' => 'discarded']],
    'config' => ['label' => $lang('nav_settings'), 'params' => ['action' => 'config']],
    'tools' => ['label' => $lang('nav_tools'), 'params' => ['action' => 'tools']],
    'logs' => ['label' => $lang('nav_logs'), 'params' => ['action' => 'logs']],
];
?>
<ul class="nav nav-tabs moloni-on__nav">
    <?php foreach ($tabs as $key => $tab) : ?>
        <li class="nav-item">
            <a class="nav-link<?= $activeTab === $key ? ' active' : '' ?>" href="<?= $e($url($tab['params'])) ?>">
                <?= $e($tab['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
    <li class="nav-item ms-auto moloni-on__nav-right">
        <?= $postForm(['op' => 'logout'], $lang('nav_logout'), ['class' => 'btn btn-sm btn-outline-danger moloni-on__logout']) ?>
    </li>
</ul>
