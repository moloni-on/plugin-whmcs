<?php

declare(strict_types=1);

namespace Moloni\Tests\Unit;

use Moloni\Enums\DocumentType;
use PHPUnit\Framework\TestCase;

final class DocumentTypeTest extends TestCase
{
    public function testInvoiceIsValid(): void
    {
        self::assertTrue(DocumentType::isValid(DocumentType::INVOICE));
        self::assertTrue(DocumentType::isValid('simplifiedInvoice'));
    }

    public function testUnknownTypeIsInvalid(): void
    {
        self::assertFalse(DocumentType::isValid('notARealType'));
        self::assertFalse(DocumentType::isValid(''));
    }

    public function testAllReturnsKnownTypes(): void
    {
        $all = DocumentType::all();

        self::assertContains(DocumentType::INVOICE, $all);
        self::assertContains(DocumentType::RECEIPT, $all);
        self::assertSame($all, array_values(array_unique($all)));
    }

    public function testHasPaymentsOnlyForPaymentCarryingTypes(): void
    {
        self::assertTrue(DocumentType::hasPayments(DocumentType::RECEIPT));
        self::assertTrue(DocumentType::hasPayments(DocumentType::INVOICE_RECEIPT));
        self::assertTrue(DocumentType::hasPayments(DocumentType::SIMPLIFIED_INVOICE));
        self::assertTrue(DocumentType::hasPayments(DocumentType::PRO_FORMA_INVOICE));
    }

    public function testHasPaymentsFalseForPlainInvoiceAndOthers(): void
    {
        // A plain invoice's payment is registered separately via a receipt.
        self::assertFalse(DocumentType::hasPayments(DocumentType::INVOICE));
        self::assertFalse(DocumentType::hasPayments(DocumentType::ESTIMATE));
        self::assertFalse(DocumentType::hasPayments(''));
    }
}
