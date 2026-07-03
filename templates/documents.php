<?php
/**
 * Documents page: documents created in Moloni ON.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var callable $paginate
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
                        <td>#<?= $e($document->order_id) ?></td>
                        <td><?= $e($document->invoice_id) ?></td>
                        <td><?= $e($document->invoice_date) ?></td>
                        <td><?= $e(number_format((float) $document->invoice_total, 2)) ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= $e($url(['op' => 'downloadPdf', 'document_id' => $document->invoice_id])) ?>"
                               target="_blank" rel="noopener">
                                <?= $e($lang('download_pdf')) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $paginate($documentsPagination, ['action' => 'documents']) ?>
    <?php endif; ?>
</div>
