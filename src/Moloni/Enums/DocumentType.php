<?php

declare(strict_types=1);

namespace Moloni\Enums;

/**
 * Moloni ON document types (API codes).
 */
final class DocumentType
{
    public const INVOICE = 'invoice';
    public const RECEIPT = 'receipt';
    public const INVOICE_RECEIPT = 'invoiceReceipt';
    public const SIMPLIFIED_INVOICE = 'simplifiedInvoice';
    public const PRO_FORMA_INVOICE = 'proFormaInvoice';
    public const PURCHASE_ORDER = 'purchaseOrder';
    public const ESTIMATE = 'estimate';
    public const BILLS_OF_LADING = 'billsOfLading';

    /**
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::INVOICE,
            self::RECEIPT,
            self::INVOICE_RECEIPT,
            self::SIMPLIFIED_INVOICE,
            self::PRO_FORMA_INVOICE,
            self::PURCHASE_ORDER,
            self::ESTIMATE,
            self::BILLS_OF_LADING,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    private function __construct()
    {
    }
}
