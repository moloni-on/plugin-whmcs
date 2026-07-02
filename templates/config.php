<?php
/**
 * Settings page.
 *
 * @var callable $lang
 * @var callable $url
 * @var callable $e
 * @var callable $csrf
 * @var array<string,string> $settings
 * @var array<int,string> $documentTypes
 * @var array<int,array<string,mixed>> $documentSets
 */
$current = static fn (string $key, string $default = ''): string => (string) ($settings[$key] ?? $default);
?>
<div class="moloni-on__panel">
    <h3><?= $e($lang('settings_title')) ?></h3>

    <form method="post" action="<?= $e($url(['action' => 'config'])) ?>">
        <?= $csrf() ?>
        <input type="hidden" name="op" value="saveSettings">

        <div class="form-group mb-3">
            <label for="document_type"><?= $e($lang('setting_document_type')) ?></label>
            <select class="form-control" id="document_type" name="document_type">
                <?php foreach ($documentTypes as $type): ?>
                    <option value="<?= $e($type) ?>"<?= $current('document_type') === $type ? ' selected' : '' ?>>
                        <?= $e($lang('doctype_' . $type)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label for="document_status"><?= $e($lang('setting_document_status')) ?></label>
            <select class="form-control" id="document_status" name="document_status">
                <option value="0"<?= $current('document_status', '0') === '0' ? ' selected' : '' ?>><?= $e($lang('status_draft')) ?></option>
                <option value="1"<?= $current('document_status') === '1' ? ' selected' : '' ?>><?= $e($lang('status_closed')) ?></option>
            </select>
        </div>

        <div class="form-group mb-3">
            <label for="document_set_id"><?= $e($lang('setting_document_set')) ?></label>
            <select class="form-control" id="document_set_id" name="document_set_id">
                <?php if (empty($documentSets)): ?>
                    <option value="<?= $e($current('document_set_id')) ?>"><?= $e($lang('setting_document_set_unavailable')) ?></option>
                <?php else: ?>
                    <?php foreach ($documentSets as $set): ?>
                        <option value="<?= $e($set['documentSetId'] ?? '') ?>"<?= $current('document_set_id') === (string) ($set['documentSetId'] ?? '') ? ' selected' : '' ?>>
                            <?= $e($set['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="tax_exemption" name="tax_exemption" value="1"<?= $current('tax_exemption') === '1' ? ' checked' : '' ?>>
            <label class="form-check-label" for="tax_exemption"><?= $e($lang('setting_tax_exemption')) ?></label>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="auto_create" name="auto_create" value="1"<?= $current('auto_create') === '1' ? ' checked' : '' ?>>
            <label class="form-check-label" for="auto_create"><?= $e($lang('setting_auto_create')) ?></label>
        </div>

        <hr>
        <h5><?= $e($lang('settings_product_mapping')) ?></h5>
        <p class="text-muted"><?= $e($lang('settings_product_mapping_help')) ?></p>

        <div class="form-group mb-3">
            <label for="measurement_unit_id"><?= $e($lang('setting_measurement_unit')) ?></label>
            <input type="number" class="form-control" id="measurement_unit_id" name="measurement_unit_id"
                   value="<?= $e($current('measurement_unit_id', '0')) ?>" min="0">
        </div>

        <div class="form-group mb-3">
            <label for="product_category_id"><?= $e($lang('setting_product_category')) ?></label>
            <input type="number" class="form-control" id="product_category_id" name="product_category_id"
                   value="<?= $e($current('product_category_id', '0')) ?>" min="0">
        </div>

        <div class="form-group mb-3">
            <label for="exemption_reason"><?= $e($lang('setting_exemption_reason')) ?></label>
            <input type="text" class="form-control" id="exemption_reason" name="exemption_reason"
                   value="<?= $e($current('exemption_reason', '')) ?>">
            <small class="text-muted"><?= $e($lang('setting_exemption_reason_help')) ?></small>
        </div>

        <button type="submit" class="btn btn-primary"><?= $e($lang('save_settings')) ?></button>
    </form>
</div>
