<?php
/**
 * Documents page: documents created in Moloni ON.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var callable $paginate
 * @var callable $orderUrl
 * @var callable $money
 * @var array<int,object> $documents
 * @var \Moloni\Support\Paginator $documentsPagination
 */
?>
<div class="moloni-on__panel">
    <h3><?= $e($lang('documents_title')) ?></h3>

    <?php if (empty($documents)) : ?>
        <p class="text-muted"><?= $e($lang('documents_empty')) ?></p>
    <?php else : ?>
        <table class="table table-striped moloni-on__table" data-moloni-table>
            <thead>
                <tr>
                    <th><?= $e($lang('col_order')) ?></th>
                    <th><?= $e($lang('col_document')) ?></th>
                    <th><?= $e($lang('col_date')) ?></th>
                    <th><?= $e($lang('col_total')) ?></th>
                    <th><?= $e($lang('col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $document) : ?>
                    <tr>
                        <td>
                            <a href="<?= $e($orderUrl((int) $document->order_id)) ?>" target="_blank" rel="noopener">
                                #<?= $e($document->ordernum ?: $document->order_id) ?>
                            </a>
                        </td>
                        <td><?= $e($document->invoice_id) ?></td>
                        <td><?= $e($document->invoice_date) ?></td>
                        <td><?= $e($money((float) $document->invoice_total, $document)) ?></td>
                        <td class="moloni-on__row-actions">
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= $e($url(['op' => 'downloadPdf', 'document_id' => $document->invoice_id])) ?>"
                               target="_blank" rel="noopener">
                                <?= $e($lang('download_pdf')) ?>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= $e($url(['op' => 'openDocument', 'document_id' => $document->invoice_id])) ?>"
                               target="_blank" rel="noopener">
                                <?= $e($lang('view_in_moloni')) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $paginate($documentsPagination, ['action' => 'documents']) ?>
    <?php endif; ?>
</div>
