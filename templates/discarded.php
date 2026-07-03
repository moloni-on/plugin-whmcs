<?php
/**
 * Discarded orders page: WHMCS orders explicitly marked "do not sync".
 *
 * @var callable $lang
 * @var callable $e
 * @var callable $postForm
 * @var callable $paginate
 * @var callable $orderUrl
 * @var callable $money
 * @var array<int,object> $discarded
 * @var \Moloni\Support\Paginator $discardedPagination
 */
?>
<div class="moloni-on__panel">
    <h3><?= $e($lang('discarded_title')) ?></h3>

    <?php if (empty($discarded)) : ?>
        <p class="text-muted"><?= $e($lang('discarded_empty')) ?></p>
    <?php else : ?>
        <table class="table moloni-on__table">
            <thead>
                <tr>
                    <th><?= $e($lang('col_order')) ?></th>
                    <th><?= $e($lang('col_customer')) ?></th>
                    <th><?= $e($lang('col_amount')) ?></th>
                    <th><?= $e($lang('col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($discarded as $order) : ?>
                    <?php $customer = trim((string) ($order->client_companyname ?: ($order->client_firstname . ' ' . $order->client_lastname))); ?>
                    <tr>
                        <td>
                            <a href="<?= $e($orderUrl((int) $order->id)) ?>" target="_blank" rel="noopener">
                                #<?= $e($order->ordernum ?: $order->id) ?>
                            </a>
                        </td>
                        <td><?= $e($customer) ?></td>
                        <td><?= $e($money((float) $order->amount, $order)) ?></td>
                        <td>
                            <?= $postForm(
                                ['op' => 'revert', 'order_id' => $order->id],
                                $lang('revert'),
                                ['class' => 'btn btn-sm btn-outline-secondary']
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $paginate($discardedPagination, ['action' => 'discarded']) ?>
    <?php endif; ?>
</div>
