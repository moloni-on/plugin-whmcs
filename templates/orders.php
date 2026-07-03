<?php
/**
 * Orders page: WHMCS orders pending sync to Moloni ON.
 *
 * The bulk form and per-row action forms are siblings (never nested):
 * checkboxes are associated with the bulk form via the HTML5 `form` attribute.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var callable $csrf
 * @var callable $postForm
 * @var callable $paginate
 * @var callable $orderUrl
 * @var callable $money
 * @var array<int,object> $orders
 * @var \Moloni\Support\Paginator $ordersPagination
 * @var array<int,string> $documentTypes
 * @var string|null $selectedDocumentType
 */
?>
<div class="moloni-on__panel">
    <h3><?= $e($lang('orders_title')) ?></h3>

    <?php if (empty($orders)) : ?>
        <p class="text-muted"><?= $e($lang('orders_empty')) ?></p>
    <?php else : ?>
        <form method="post" action="<?= $e($url()) ?>" id="moloni-bulk-form" class="moloni-on__toolbar">
            <?= $csrf() ?>
            <input type="hidden" name="op" value="bulkCreate">
            <select name="document_type" class="form-control moloni-on__doc-type">
                <?php foreach ($documentTypes as $type) : ?>
                    <option value="<?= $e($type) ?>"<?= ($selectedDocumentType ?? null) === $type ? ' selected' : '' ?>><?= $e($lang('doctype_' . $type)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" data-moloni-confirm="<?= $e($lang('confirm_bulk')) ?>">
                <?= $e($lang('create_selected')) ?>
            </button>
        </form>

        <table class="table table-striped moloni-on__table" data-moloni-table>
            <thead>
                <tr>
                    <th><input type="checkbox" data-moloni-check-all></th>
                    <th><?= $e($lang('col_order')) ?></th>
                    <th><?= $e($lang('col_customer')) ?></th>
                    <th><?= $e($lang('col_amount')) ?></th>
                    <th><?= $e($lang('col_date')) ?></th>
                    <th><?= $e($lang('col_status')) ?></th>
                    <th><?= $e($lang('col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order) : ?>
                    <?php
                    $customer = trim((string) ($order->client_companyname ?: ($order->client_firstname . ' ' . $order->client_lastname)));
                    $isFailed = ($order->sync_status ?? '') === 'failed';
                    ?>
                    <tr>
                        <td><input type="checkbox" name="order_ids[]" value="<?= $e($order->id) ?>" form="moloni-bulk-form"></td>
                        <td>
                            <a href="<?= $e($orderUrl((int) $order->id)) ?>" target="_blank" rel="noopener">
                                #<?= $e($order->ordernum ?: $order->id) ?>
                            </a>
                        </td>
                        <td>
                            <?= $e($customer) ?><br>
                            <small class="text-muted"><?= $e($order->client_email) ?></small>
                        </td>
                        <td><?= $e($money((float) $order->amount, $order)) ?></td>
                        <td><?= $e($order->date) ?></td>
                        <td>
                            <?php if ($isFailed) : ?>
                                <span class="badge bg-danger" title="<?= $e((string) $order->error_message) ?>"><?= $e($lang('status_failed')) ?></span>
                            <?php else : ?>
                                <span class="badge bg-secondary"><?= $e($lang('status_pending')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="moloni-on__row-actions">
                            <?= $postForm(
                                ['op' => 'createDocument', 'order_id' => $order->id],
                                $lang('create'),
                                ['class' => 'btn btn-sm btn-success']
                            ) ?>
                            <?= $postForm(
                                ['op' => 'discard', 'order_id' => $order->id],
                                $lang('discard'),
                                ['class' => 'btn btn-sm btn-outline-secondary', 'confirm' => $lang('confirm_discard')]
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $paginate($ordersPagination, ['action' => 'orders']) ?>
    <?php endif; ?>
</div>
