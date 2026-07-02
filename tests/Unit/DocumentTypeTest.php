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
}
