<?php

declare(strict_types=1);

namespace Moloni\Api;

use Moloni\Exceptions\ApiException;
use Moloni\GraphQL\AbstractOperation;
use Moloni\GraphQL\Mutations\CreateCustomer;
use Moloni\GraphQL\Mutations\CreateDocument;
use Moloni\GraphQL\Mutations\CreatePaymentMethod;
use Moloni\GraphQL\Mutations\CreateProduct;
use Moloni\GraphQL\Mutations\CreateTax;
use Moloni\GraphQL\Mutations\UpdateCustomer;
use Moloni\GraphQL\Mutations\UpdateDocumentStatus;
use Moloni\GraphQL\Queries\GetCompanies;
use Moloni\GraphQL\Queries\GetCompany;
use Moloni\GraphQL\Queries\GetCountries;
use Moloni\GraphQL\Queries\GetCustomerNextNumber;
use Moloni\GraphQL\Queries\GetCustomers;
use Moloni\GraphQL\Queries\GetDocument;
use Moloni\GraphQL\Queries\GetDocumentPdfToken;
use Moloni\GraphQL\Queries\GetDocumentSets;
use Moloni\GraphQL\Queries\GetMe;
use Moloni\GraphQL\Queries\GetPaymentMethods;
use Moloni\GraphQL\Queries\GetProducts;
use Moloni\GraphQL\Queries\GetTaxes;

/**
 * High-level, domain-oriented wrapper over {@see ApiClient}.
 *
 * Each method runs one GraphQL operation and returns the meaningful `data`
 * node, hiding the envelope shape from services.
 */
class MoloniClient
{
    private ApiClient $api;

    public function __construct(ApiClient $api)
    {
        $this->api = $api;
    }

    /**
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function getMe(): array
    {
        return $this->run(new GetMe());
    }

    /**
     * @return array<int,array<string,mixed>>
     * @throws ApiException
     */
    public function getCompanies(): array
    {
        return $this->run(new GetCompanies());
    }

    /**
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function getCompany(int $companyId): array
    {
        return $this->run(new GetCompany(), ['companyId' => $companyId]);
    }

    /**
     * @return array<int,array<string,mixed>>
     * @throws ApiException
     */
    public function getDocumentSets(): array
    {
        return $this->run(new GetDocumentSets());
    }

    /**
     * Find a customer by VAT number, or null when none matches.
     *
     * @return array<string,mixed>|null
     * @throws ApiException
     */
    public function findCustomerByVat(string $vat): ?array
    {
        $customers = $this->run(new GetCustomers(), ['vat' => $vat]);

        return $customers[0] ?? null;
    }

    /**
     * Find a customer by e-mail, ignoring customers that carry a VAT (those
     * are matched by VAT instead), or null when none matches.
     *
     * @return array<string,mixed>|null
     * @throws ApiException
     */
    public function findCustomerByEmail(string $email): ?array
    {
        $customers = $this->run(new GetCustomers(), ['email' => $email]);

        foreach ($customers as $customer) {
            if (empty($customer['vat'])) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $data CustomerInsert fields.
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function createCustomer(array $data): array
    {
        return $this->run(new CreateCustomer(), $data);
    }

    /**
     * @param array<string,mixed> $data CustomerUpdate fields (must include customerId).
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function updateCustomer(array $data): array
    {
        return $this->run(new UpdateCustomer(), $data);
    }

    /**
     * Find a payment method by exact name, or null when none matches.
     *
     * @return array<string,mixed>|null
     * @throws ApiException
     */
    public function findPaymentMethodByName(string $name): ?array
    {
        $methods = $this->run(new GetPaymentMethods(), ['name' => $name]);

        foreach ($methods as $method) {
            if (($method['name'] ?? null) === $name) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $data PaymentMethodInsert fields.
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function createPaymentMethod(array $data): array
    {
        return $this->run(new CreatePaymentMethod(), $data);
    }

    /**
     * The next sequential customer number (required to create a customer).
     *
     * @throws ApiException
     */
    public function getCustomerNextNumber(): ?string
    {
        $op = new GetCustomerNextNumber();
        $response = $this->api->request($op->operation(), $op->query(), $op->variables());
        $value = $response['data'][$op->operation()]['data'] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * All Moloni ON countries (countryId, iso3166_1, language).
     *
     * @return array<int,array<string,mixed>>
     * @throws ApiException
     */
    public function getCountries(): array
    {
        return $this->run(new GetCountries());
    }

    /**
     * Find a product by its reference, or null when none matches.
     *
     * @return array<string,mixed>|null
     * @throws ApiException
     */
    public function findProductByReference(string $reference): ?array
    {
        $products = $this->run(new GetProducts(), ['reference' => $reference]);

        return $products[0] ?? null;
    }

    /**
     * @param array<string,mixed> $data ProductInsert fields.
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function createProduct(array $data): array
    {
        return $this->run(new CreateProduct(), $data);
    }

    /**
     * Find a VAT tax matching a rate within a fiscal zone, or null.
     *
     * @return array<string,mixed>|null
     * @throws ApiException
     */
    public function findTax(float $rate, string $fiscalZoneCode): ?array
    {
        $taxes = $this->run(new GetTaxes(), ['rate' => $rate, 'code' => $fiscalZoneCode]);

        return $taxes[0] ?? null;
    }

    /**
     * @param array<string,mixed> $data TaxInsert fields.
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function createTax(array $data): array
    {
        return $this->run(new CreateTax(), $data);
    }

    /**
     * @param array<string,mixed> $data <Type>Insert fields.
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function createDocument(array $data, string $documentType = 'invoice'): array
    {
        return $this->run(new CreateDocument($documentType), $data);
    }

    /**
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function updateDocumentStatus(int $documentId, int $status, string $documentType = 'invoice'): array
    {
        return $this->run(
            new UpdateDocumentStatus($documentType),
            ['documentId' => $documentId, 'status' => $status]
        );
    }

    /**
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function getDocument(int $documentId): array
    {
        return $this->run(new GetDocument(), ['documentId' => $documentId]);
    }

    /**
     * Returns {token, filename, path} to build a media-API PDF download URL.
     *
     * @return array<string,mixed>
     * @throws ApiException
     */
    public function getDocumentPdfToken(int $documentId, string $documentType = 'invoice'): array
    {
        return $this->run(new GetDocumentPdfToken($documentType), ['documentId' => $documentId]);
    }

    /**
     * Execute an operation and return its unwrapped `data` node.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     * @throws ApiException
     */
    private function run(AbstractOperation $operation, array $input = []): array
    {
        $response = $this->api->request(
            $operation->operation(),
            $operation->query(),
            $operation->variables($input)
        );

        return $response['data'][$operation->operation()]['data'] ?? [];
    }
}
