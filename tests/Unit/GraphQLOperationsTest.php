<?php

declare(strict_types=1);

namespace Moloni\Tests\Unit;

use Moloni\Enums\TaxFiscalZoneType;
use Moloni\Enums\TaxType;
use Moloni\GraphQL\Mutations\CreateDocument;
use Moloni\GraphQL\Mutations\UpdateDocumentStatus;
use Moloni\GraphQL\Queries\GetCompany;
use Moloni\GraphQL\Queries\GetDocumentPdfToken;
use Moloni\GraphQL\Queries\GetProducts;
use Moloni\GraphQL\Queries\GetTaxes;
use PHPUnit\Framework\TestCase;

final class GraphQLOperationsTest extends TestCase
{
    public function testCreateDocumentWrapsDataAndNamesOperation(): void
    {
        $op = new CreateDocument();

        self::assertSame('invoiceCreate', $op->operation());
        self::assertStringContainsString('mutation invoiceCreate', $op->query());
        self::assertSame(['data' => ['customerId' => 5]], $op->variables(['customerId' => 5]));
    }

    public function testUpdateDocumentStatusCoercesTypes(): void
    {
        $op = new UpdateDocumentStatus();

        $variables = $op->variables(['documentId' => '10', 'status' => '1']);

        self::assertSame(['data' => ['documentId' => 10, 'status' => 1]], $variables);
    }

    public function testGetCompanyBuildsCompanyId(): void
    {
        $op = new GetCompany();

        self::assertSame('company', $op->operation());
        self::assertSame(['companyId' => 7], $op->variables(['companyId' => '7']));
    }

    public function testGetDocumentPdfTokenBuildsDocumentId(): void
    {
        $op = new GetDocumentPdfToken();

        self::assertSame('invoiceGetPDFToken', $op->operation());
        self::assertSame(['documentId' => 99], $op->variables(['documentId' => 99]));
    }

    public function testDocumentTypeParametrizesOperationAndInputType(): void
    {
        $create = new CreateDocument('simplifiedInvoice');
        self::assertSame('simplifiedInvoiceCreate', $create->operation());
        self::assertStringContainsString('$data: SimplifiedInvoiceInsert!', $create->query());

        $update = new UpdateDocumentStatus('receipt');
        self::assertSame('receiptUpdate', $update->operation());
        self::assertStringContainsString('$data: ReceiptUpdate!', $update->query());

        $pdf = new GetDocumentPdfToken('proFormaInvoice');
        self::assertSame('proFormaInvoiceGetPDFToken', $pdf->operation());
    }

    public function testGetProductsFiltersByReference(): void
    {
        $op = new GetProducts();
        $variables = $op->variables(['reference' => 'WHMCS-hosting']);

        self::assertSame(
            [['field' => 'reference', 'comparison' => 'eq', 'value' => 'WHMCS-hosting']],
            array_slice($variables['options']['filter'], 0, 1)
        );
    }

    public function testGetTaxesFiltersByRateAndZone(): void
    {
        $op = new GetTaxes();
        $variables = $op->variables(['rate' => 23, 'code' => 'PT']);

        $filter = $variables['options']['filter'];
        self::assertContains(['field' => 'value', 'comparison' => 'eq', 'value' => '23'], $filter);
        self::assertContains(
            ['field' => 'type', 'comparison' => 'eq', 'value' => (string) TaxType::PERCENTAGE],
            $filter
        );
        self::assertContains(
            ['field' => 'fiscalZoneFinanceType', 'comparison' => 'eq', 'value' => (string) TaxFiscalZoneType::VAT],
            $filter
        );
        // Fiscal zone code is lower-cased for the search.
        self::assertSame(['field' => 'fiscalZone', 'value' => 'pt'], $variables['options']['search']);
    }
}
