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
 * @var array<int,array<string,mixed>> $measurementUnits
 * @var array<int,array<string,mixed>> $productCategories
 * @var array<int,array<string,mixed>> $paymentMethods
 * @var array<int,array{code:string,name:string}> $exemptionReasons
 * @var array<int,string> $productCustomFields
 */
$current = static fn (string $key, string $default = ''): string => (string) ($settings[$key] ?? $default);
?>
<div class="moloni-on__panel">
    <h3><?= $e($lang('settings_title')) ?></h3>

    <form method="post" action="<?= $e($url(['action' => 'config'])) ?>">
        <?= $csrf() ?>
        <input type="hidden" name="op" value="saveSettings">

        <h5><?= $e($lang('settings_document_defaults')) ?></h5>
        <p class="text-muted"><?= $e($lang('settings_document_defaults_help')) ?></p>

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

        <div class="form-group mb-3">
            <label for="payment_method_id"><?= $e($lang('setting_payment_method')) ?></label>
            <select class="form-control" id="payment_method_id" name="payment_method_id">
                <option value="0"<?= $current('payment_method_id', '0') === '0' ? ' selected' : '' ?>><?= $e($lang('setting_option_none')) ?></option>
                <?php foreach ($paymentMethods as $method): ?>
                    <?php $methodId = (string) ($method['paymentMethodId'] ?? ''); ?>
                    <option value="<?= $e($methodId) ?>"<?= $current('payment_method_id') === $methodId ? ' selected' : '' ?>>
                        <?= $e($method['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted"><?= $e($lang('setting_payment_method_help')) ?></small>
        </div>

        <hr>
        <h5><?= $e($lang('settings_automation')) ?></h5>
        <p class="text-muted"><?= $e($lang('settings_automation_help')) ?></p>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="auto_create" name="auto_create" value="1"<?= $current('auto_create') === '1' ? ' checked' : '' ?>>
            <label class="form-check-label" for="auto_create"><?= $e($lang('setting_auto_create')) ?></label>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="send_email" name="send_email" value="1"<?= $current('send_email') === '1' ? ' checked' : '' ?>>
            <label class="form-check-label" for="send_email"><?= $e($lang('setting_send_email')) ?></label>
            <small class="d-block text-muted"><?= $e($lang('setting_send_email_help')) ?></small>
        </div>

        <hr>
        <h5><?= $e($lang('settings_product_mapping')) ?></h5>
        <p class="text-muted"><?= $e($lang('settings_product_mapping_help')) ?></p>

        <div class="form-group mb-3">
            <label for="measurement_unit_id"><?= $e($lang('setting_measurement_unit')) ?></label>
            <select class="form-control" id="measurement_unit_id" name="measurement_unit_id">
                <option value="0"<?= $current('measurement_unit_id', '0') === '0' ? ' selected' : '' ?>><?= $e($lang('setting_option_none')) ?></option>
                <?php foreach ($measurementUnits as $unit): ?>
                    <?php $unitId = (string) ($unit['measurementUnitId'] ?? ''); ?>
                    <?php $abbr = trim((string) ($unit['abbreviation'] ?? '')); ?>
                    <option value="<?= $e($unitId) ?>"<?= $current('measurement_unit_id') === $unitId ? ' selected' : '' ?>>
                        <?= $e(($unit['name'] ?? '') . ($abbr !== '' ? ' (' . $abbr . ')' : '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label for="product_category_id"><?= $e($lang('setting_product_category')) ?></label>
            <select class="form-control" id="product_category_id" name="product_category_id">
                <option value="0"<?= $current('product_category_id', '0') === '0' ? ' selected' : '' ?>><?= $e($lang('setting_option_none')) ?></option>
                <?php foreach ($productCategories as $category): ?>
                    <?php $categoryId = (string) ($category['productCategoryId'] ?? ''); ?>
                    <option value="<?= $e($categoryId) ?>"<?= $current('product_category_id') === $categoryId ? ' selected' : '' ?>>
                        <?= $e($category['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label for="custom_reference"><?= $e($lang('setting_custom_reference')) ?></label>
            <select class="form-control" id="custom_reference" name="custom_reference">
                <option value=""<?= $current('custom_reference') === '' ? ' selected' : '' ?>><?= $e($lang('setting_option_none')) ?></option>
                <?php foreach ($productCustomFields as $fieldName): ?>
                    <option value="<?= $e($fieldName) ?>"<?= $current('custom_reference') === (string) $fieldName ? ' selected' : '' ?>>
                        <?= $e($fieldName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted"><?= $e($lang('setting_custom_reference_help')) ?></small>
        </div>

        <hr>
        <h5><?= $e($lang('settings_customer_mapping')) ?></h5>

        <div class="form-group mb-3">
            <label for="fiscal_zone_based_on"><?= $e($lang('setting_fiscal_zone_based_on')) ?></label>
            <select class="form-control" id="fiscal_zone_based_on" name="fiscal_zone_based_on">
                <option value="company"<?= $current('fiscal_zone_based_on', 'company') === 'company' ? ' selected' : '' ?>><?= $e($lang('fiscal_zone_company')) ?></option>
                <option value="billing"<?= $current('fiscal_zone_based_on') === 'billing' ? ' selected' : '' ?>><?= $e($lang('fiscal_zone_billing')) ?></option>
            </select>
            <small class="text-muted"><?= $e($lang('setting_fiscal_zone_based_on_help')) ?></small>
        </div>

        <div class="form-group mb-3">
            <label for="vat_field"><?= $e($lang('setting_vat_field')) ?></label>
            <input type="text" class="form-control" id="vat_field" name="vat_field"
                   value="<?= $e($current('vat_field', '')) ?>">
            <small class="text-muted"><?= $e($lang('setting_vat_field_help')) ?></small>
        </div>

        <div class="form-group mb-3">
            <label for="exemption_reason"><?= $e($lang('setting_exemption_reason')) ?></label>
            <?php if (!empty($exemptionReasons)): ?>
                <select class="form-control" id="exemption_reason" name="exemption_reason">
                    <option value=""<?= $current('exemption_reason') === '' ? ' selected' : '' ?>><?= $e($lang('setting_option_none')) ?></option>
                    <?php foreach ($exemptionReasons as $reason): ?>
                        <?php $code = (string) ($reason['code'] ?? ''); ?>
                        <option value="<?= $e($code) ?>" title="<?= $e($reason['name'] ?? '') ?>"<?= $current('exemption_reason') === $code ? ' selected' : '' ?>>
                            <?= $e($code . ' - ' . ($reason['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" class="form-control" id="exemption_reason" name="exemption_reason"
                       value="<?= $e($current('exemption_reason', '')) ?>">
            <?php endif; ?>
            <small class="text-muted"><?= $e($lang('setting_exemption_reason_help')) ?></small>
        </div>

        <button type="submit" class="btn btn-primary"><?= $e($lang('save_settings')) ?></button>
    </form>
</div>
