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
    public const TAX_EXEMPTION = 'tax_exemption';
    public const AUTO_CREATE = 'auto_create';
    public const MEASUREMENT_UNIT_ID = 'measurement_unit_id';
    public const PRODUCT_CATEGORY_ID = 'product_category_id';
    public const EXEMPTION_REASON = 'exemption_reason';

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

    public function taxExemption(): bool
    {
        return (string) $this->get(self::TAX_EXEMPTION, '0') === '1';
    }

    public function autoCreate(): bool
    {
        return (string) $this->get(self::AUTO_CREATE, '0') === '1';
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
     * Moloni exemption reason code applied to lines with a 0% tax rate.
     */
    public function exemptionReason(): string
    {
        return (string) $this->get(self::EXEMPTION_REASON, '');
    }
}
