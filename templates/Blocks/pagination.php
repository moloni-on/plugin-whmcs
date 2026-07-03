<?php
/**
 * Reusable pagination controls for an admin list view.
 *
 * Renders nothing when the list fits on a single page. Page links preserve the
 * surrounding query params ($baseParams: e.g. the active tab and any filters)
 * and set $pageParam to the target page, so a page can carry more than one
 * paginated list by giving each a distinct $pageParam.
 *
 * @var callable                    $lang
 * @var callable                    $url
 * @var callable                    $e
 * @var \Moloni\Support\Paginator   $paginator
 * @var array<string,string|int>    $baseParams query params to preserve in links
 * @var string                      $pageParam  name of the page query parameter
 */
if ($paginator->pageCount() <= 1) {
    return;
}
$link = static fn (int $n): string => $url([$pageParam => $n] + $baseParams);
?>
<nav class="moloni-on__pagination" aria-label="<?= $e($lang('pagination_label')) ?>">
    <span class="moloni-on__pagination-summary">
        <?= $e($lang('pagination_summary', [
            'from' => $paginator->from(),
            'to' => $paginator->to(),
            'total' => $paginator->total(),
        ])) ?>
    </span>
    <ul class="pagination pagination-sm">
        <li class="page-item<?= $paginator->hasPrev() ? '' : ' disabled' ?>">
            <a class="page-link" href="<?= $e($paginator->hasPrev() ? $link($paginator->page() - 1) : '#') ?>">
                <?= $e($lang('pagination_prev')) ?>
            </a>
        </li>
        <?php foreach ($paginator->pages() as $n) : ?>
            <?php if ($n === 0) : ?>
                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
            <?php else : ?>
                <li class="page-item<?= $n === $paginator->page() ? ' active' : '' ?>">
                    <a class="page-link" href="<?= $e($link($n)) ?>"><?= $e($n) ?></a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item<?= $paginator->hasNext() ? '' : ' disabled' ?>">
            <a class="page-link" href="<?= $e($paginator->hasNext() ? $link($paginator->page() + 1) : '#') ?>">
                <?= $e($lang('pagination_next')) ?>
            </a>
        </li>
    </ul>
</nav>
