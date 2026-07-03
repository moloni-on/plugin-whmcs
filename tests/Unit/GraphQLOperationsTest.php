<?php

declare(strict_types=1);

namespace Moloni\Tests\Unit;

use Moloni\Enums\TaxFiscalZoneType;
use Moloni\Enums\TaxType;
use Moloni\GraphQL\Mutations\CreateDocument;
use Moloni\GraphQL\Mutations\CreateDocumentPdf;
use Moloni\GraphQL\Mutations\CreatePaymentMethod;
use Moloni\GraphQL\Mutations\SendDocumentMail;
use Moloni\GraphQL\Mutations\UpdateCustomer;
use Moloni\GraphQL\Mutations\UpdateDocumentStatus;
use Moloni\GraphQL\Queries\GetCompany;
use Moloni\GraphQL\Queries\GetCurrencyExchanges;
use Moloni\GraphQL\Queries\GetCustomers;
use Moloni\GraphQL\Queries\GetDocumentPdfToken;
use Moloni\GraphQL\Queries\GetDocumentSets;
use Moloni\GraphQL\Queries\GetMeasurementUnits;
use Moloni\GraphQL\Queries\GetPaymentMethods;
use Moloni\GraphQL\Queries\GetProductCategories;
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

        $update = new UpdateDocumentStatus('invoiceReceipt');
        self::assertSame('invoiceReceiptUpdate', $update->operation());
        self::assertStringContainsString('$data: InvoiceReceiptUpdate!', $update->query());

        $pdf = new GetDocumentPdfToken('proFormaInvoice');
        self::assertSame('proFormaInvoiceGetPDFToken', $pdf->operation());
    }

    public function testGetDocumentSetsAlwaysSendsPagination(): void
    {
        $op = new GetDocumentSets();

        self::assertSame('documentSets', $op->operation());
        // The API rejects the query without pagination options, so they must be
        // sent on every call regardless of input.
        self::assertSame(
            ['options' => ['pagination' => ['page' => 1, 'qty' => 100]]],
            $op->variables()
        );
    }

    public function testCreateDocumentPdfNamesOperationByType(): void
    {
        $op = new CreateDocumentPdf('proFormaInvoice');

        self::assertSame('proFormaInvoiceGetPDF', $op->operation());
        self::assertStringContainsString('mutation proFormaInvoiceGetPDF', $op->query());
        self::assertSame(['documentId' => 55], $op->variables(['documentId' => '55']));
    }

    public function testSendDocumentMailBuildsRecipientAndAttaches(): void
    {
        $op = new SendDocumentMail('simplifiedInvoice');

        self::assertSame('simplifiedInvoiceSendMail', $op->operation());
        self::assertStringContainsString('$documents: [Int]!', $op->query());

        $variables = $op->variables(['documentId' => '42', 'name' => 'Acme', 'email' => 'a@b.pt']);

        self::assertSame([42], $variables['documents']);
        self::assertSame(['name' => 'Acme', 'email' => 'a@b.pt'], $variables['mailData']['to']);
        self::assertTrue($variables['mailData']['attachment']);
    }

    public function testGetMeasurementUnitsAlwaysSendsPagination(): void
    {
        $op = new GetMeasurementUnits();

        self::assertSame('measurementUnits', $op->operation());
        self::assertSame(
            ['options' => ['pagination' => ['page' => 1, 'qty' => 100]]],
            $op->variables()
        );
    }

    public function testGetProductCategoriesFiltersToRootAndPaginates(): void
    {
        $op = new GetProductCategories();
        $variables = $op->variables();

        self::assertSame('productCategories', $op->operation());
        // Root categories only: a null parent.
        self::assertContains(
            ['field' => 'parentId', 'comparison' => 'eq', 'value' => null],
            $variables['options']['filter']
        );
        self::assertSame(['page' => 1, 'qty' => 100], $variables['options']['pagination']);
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

    public function testGetCurrencyExchangesSearchesByPair(): void
    {
        $op = new GetCurrencyExchanges();
        $variables = $op->variables(['from' => 'eur', 'to' => 'usd']);

        self::assertSame('currencyExchanges', $op->operation());
        // The pair is matched by an upper-cased "FROM TO" search value.
        self::assertSame(['field' => 'pair', 'value' => 'EUR USD'], $variables['options']['search']);
        // The API rejects list queries without pagination, so it is always sent.
        self::assertSame(['page' => 1, 'qty' => 50], $variables['options']['pagination']);
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
        // Pagination is always sent; a name search is added when provided.
        self::assertSame(
            ['options' => ['pagination' => ['page' => 1, 'qty' => 100]]],
            $query->variables()
        );
        self::assertSame(
            [
                'options' => [
                    'pagination' => ['page' => 1, 'qty' => 100],
                    'search' => ['field' => 'name', 'value' => 'PayPal'],
                ],
            ],
            $query->variables(['name' => 'PayPal'])
        );

        $create = new CreatePaymentMethod();
        self::assertSame('paymentMethodCreate', $create->operation());
        self::assertSame(['data' => ['name' => 'PayPal']], $create->variables(['name' => 'PayPal']));
    }
}
