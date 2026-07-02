<?php
/**
 * Primary navigation tabs.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var string $activeTab
 */
$tabs = [
    'orders' => ['label' => $lang('nav_orders'), 'params' => []],
    'documents' => ['label' => $lang('nav_documents'), 'params' => ['action' => 'documents']],
    'config' => ['label' => $lang('nav_settings'), 'params' => ['action' => 'config']],
    'tools' => ['label' => $lang('nav_tools'), 'params' => ['action' => 'tools']],
    'logs' => ['label' => $lang('nav_logs'), 'params' => ['action' => 'logs']],
];
?>
<ul class="nav nav-tabs moloni-on__nav">
    <?php foreach ($tabs as $key => $tab): ?>
        <li class="nav-item">
            <a class="nav-link<?= $activeTab === $key ? ' active' : '' ?>" href="<?= $e($url($tab['params'])) ?>">
                <?= $e($tab['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
    <li class="nav-item ms-auto moloni-on__nav-right">
        <a class="nav-link" href="<?= $e($url(['action' => 'logout'])) ?>"><?= $e($lang('nav_logout')) ?></a>
    </li>
</ul>
