<?php

declare(strict_types=1);

namespace Moloni\Tests\Unit;

use Moloni\Enums\TaxFiscalZoneType;
use Moloni\Enums\TaxType;
use Moloni\GraphQL\Mutations\CreateDocument;
use Moloni\GraphQL\Mutations\CreatePaymentMethod;
use Moloni\GraphQL\Mutations\UpdateCustomer;
use Moloni\GraphQL\Mutations\UpdateDocumentStatus;
use Moloni\GraphQL\Queries\GetCompany;
use Moloni\GraphQL\Queries\GetCustomers;
use Moloni\GraphQL\Queries\GetDocumentPdfToken;
use Moloni\GraphQL\Queries\GetPaymentMethods;
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

    public function testGetCustomersSearchesByVatOrEmail(): void
    {
        $op = new GetCustomers();

        self::assertSame(
            ['options' => ['search' => ['field' => 'vat', 'value' => '123456789']]],
            $op->variables(['vat' => '123456789'])
        );
        self::assertSame(
            ['options' => ['search' => ['field' => 'email', 'value' => 'a@b.pt']]],
            $op->variables(['email' => 'a@b.pt'])
        );
        // VAT wins when both are present; no identifier yields no filter.
        self::assertSame('vat', $op->variables(['vat' => '1', 'email' => 'a@b.pt'])['options']['search']['field']);
        self::assertSame([], $op->variables([]));
    }

    public function testUpdateCustomerWrapsData(): void
    {
        $op = new UpdateCustomer();

        self::assertSame('customerUpdate', $op->operation());
        self::assertSame(
            ['data' => ['customerId' => 5, 'name' => 'X']],
            $op->variables(['customerId' => 5, 'name' => 'X'])
        );
    }

    public function testPaymentMethodOperations(): void
    {
        $query = new GetPaymentMethods();
        self::assertSame('paymentMethods', $query->operation());
        self::assertSame(
            ['options' => ['search' => ['field' => 'name', 'value' => 'PayPal']]],
            $query->variables(['name' => 'PayPal'])
        );

        $create = new CreatePaymentMethod();
        self::assertSame('paymentMethodCreate', $create->operation());
        self::assertSame(['data' => ['name' => 'PayPal']], $create->variables(['name' => 'PayPal']));
    }
}
