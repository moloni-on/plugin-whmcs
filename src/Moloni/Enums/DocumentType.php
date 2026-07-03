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

    /**
     * Document types the module offers as billing targets. Receipts are
     * intentionally excluded — they are not used to invoice an order.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::INVOICE,
            self::INVOICE_RECEIPT,
            self::SIMPLIFIED_INVOICE,
            self::PRO_FORMA_INVOICE,
            self::PURCHASE_ORDER,
            self::ESTIMATE,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Document types that carry payment entries. A plain invoice does not — its
     * payment is registered separately via a receipt — so payments are only
     * added to receipts, invoice-receipts and pro-forma/simplified invoices.
     */
    public static function hasPayments(string $type): bool
    {
        return in_array($type, [
            self::RECEIPT,
            self::INVOICE_RECEIPT,
            self::PRO_FORMA_INVOICE,
            self::SIMPLIFIED_INVOICE,
        ], true);
    }

    private function __construct()
    {
    }
}
