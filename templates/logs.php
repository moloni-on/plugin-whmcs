<?php
/**
 * Logs page.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var callable $postForm
 * @var array<int,object> $logs
 */
$levels = ['', 'debug', 'info', 'notice', 'warning', 'error', 'critical'];
$activeLevel = (string) ($_GET['level'] ?? '');
?>
<div class="moloni-on__panel">
    <div class="moloni-on__toolbar">
        <h3><?= $e($lang('logs_title')) ?></h3>
        <div class="moloni-on__toolbar-actions">
            <form method="get" action="<?= $e($url()) ?>" class="moloni-on__filter">
                <input type="hidden" name="module" value="moloni_on">
                <input type="hidden" name="action" value="logs">
                <select name="level" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($levels as $level): ?>
                        <option value="<?= $e($level) ?>"<?= $activeLevel === $level ? ' selected' : '' ?>>
                            <?= $e($level === '' ? $lang('logs_all_levels') : ucfirst($level)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?= $postForm(
                ['op' => 'clearLogs', 'action' => 'logs'],
                $lang('clear_logs'),
                ['class' => 'btn btn-outline-danger', 'confirm' => $lang('confirm_clear_logs')]
            ) ?>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <p class="text-muted"><?= $e($lang('logs_empty')) ?></p>
    <?php else: ?>
        <table class="table table-striped moloni-on__table" data-moloni-table>
            <thead>
                <tr>
                    <th><?= $e($lang('col_timestamp')) ?></th>
                    <th><?= $e($lang('col_level')) ?></th>
                    <th><?= $e($lang('col_message')) ?></th>
                    <th><?= $e($lang('col_order')) ?></th>
                    <th><?= $e($lang('col_context')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $e($log->timestamp) ?></td>
                        <td><span class="badge moloni-on__level moloni-on__level--<?= $e($log->level) ?>"><?= $e($log->level) ?></span></td>
                        <td><?= $e($log->message) ?></td>
                        <td><?= $e($log->order_id ?? '') ?></td>
                        <td>
                            <?php if (!empty($log->context)): ?>
                                <code class="moloni-on__context"><?= $e($log->context) ?></code>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
