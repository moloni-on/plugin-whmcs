<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Enums\DocumentStatus;
use Moloni\Enums\DocumentType;
use Moloni\Models\Config;

/**
 * Reads and writes module settings (backed by mod_moloni_on_config).
 */
class SettingsService
{
    public const DOCUMENT_TYPE = 'document_type';
    public const DOCUMENT_STATUS = 'document_status';
    public const DOCUMENT_SET_ID = 'document_set_id';
    public const AUTO_CREATE = 'auto_create';
    public const SEND_EMAIL = 'send_email';
    public const MEASUREMENT_UNIT_ID = 'measurement_unit_id';
    public const PRODUCT_CATEGORY_ID = 'product_category_id';
    public const PAYMENT_METHOD_ID = 'payment_method_id';
    public const EXEMPTION_REASON = 'exemption_reason';
    public const FISCAL_ZONE_BASED_ON = 'fiscal_zone_based_on';
    public const VAT_FIELD = 'vat_field';

    /** Document fiscal zone follows the Moloni company's own zone. */
    public const FISCAL_ZONE_COMPANY = 'company';
    /** Document fiscal zone follows the client's billing country. */
    public const FISCAL_ZONE_BILLING = 'billing';

    public function get(string $key, ?string $default = null): ?string
    {
        return Config::get($key, $default);
    }

    public function set(string $key, ?string $value): void
    {
        Config::set($key, $value);
    }

    /**
     * @return array<string,string>
     */
    public function all(): array
    {
        return Config::all();
    }

    public function documentType(): string
    {
        $type = (string) $this->get(self::DOCUMENT_TYPE, DocumentType::INVOICE);

        return DocumentType::isValid($type) ? $type : DocumentType::INVOICE;
    }

    public function documentStatus(): int
    {
        return (int) $this->get(self::DOCUMENT_STATUS, (string) DocumentStatus::DRAFT);
    }

    public function documentSetId(): int
    {
        return (int) $this->get(self::DOCUMENT_SET_ID, '0');
    }

    public function autoCreate(): bool
    {
        return (string) $this->get(self::AUTO_CREATE, '0') === '1';
    }

    /**
     * Whether to e-mail the document to the customer after creating it. Only
     * takes effect once the document is actually closed (see DocumentService).
     */
    public function sendEmail(): bool
    {
        return (string) $this->get(self::SEND_EMAIL, '0') === '1';
    }

    public function measurementUnitId(): int
    {
        return (int) $this->get(self::MEASUREMENT_UNIT_ID, '0');
    }

    public function productCategoryId(): int
    {
        return (int) $this->get(self::PRODUCT_CATEGORY_ID, '0');
    }

    /**
     * Default Moloni payment method used when a WHMCS gateway cannot be matched
     * to a payment method by name. 0 means none configured.
     */
    public function paymentMethodId(): int
    {
        return (int) $this->get(self::PAYMENT_METHOD_ID, '0');
    }

    /**
     * Moloni exemption reason code automatically applied to any line that
     * resolves to no VAT (a 0% / untaxed line).
     */
    public function exemptionReason(): string
    {
        return (string) $this->get(self::EXEMPTION_REASON, '');
    }

    /**
     * Where a document's fiscal zone is taken from: the Moloni company's own
     * zone (default) or the client's billing country.
     */
    public function fiscalZoneBasedOn(): string
    {
        $value = (string) $this->get(self::FISCAL_ZONE_BASED_ON, self::FISCAL_ZONE_COMPANY);

        return $value === self::FISCAL_ZONE_BILLING ? self::FISCAL_ZONE_BILLING : self::FISCAL_ZONE_COMPANY;
    }

    /**
     * Name of the WHMCS client custom field holding the VAT/tax number. When
     * empty (or the client has no value), the native tblclients.tax_id is used.
     */
    public function vatField(): string
    {
        return trim((string) $this->get(self::VAT_FIELD, ''));
    }
}
