<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Enums\DocumentType;
use Moloni\Exceptions\ApiException;
use Moloni\Exceptions\DocumentException;
use Moloni\Exceptions\ValidationException;
use Moloni\Facades\LoggerFacade;
use Moloni\Models\Document;
use Moloni\Models\Order;
use Moloni\Models\Whmcs;
use Moloni\Support\Context;
use Moloni\Support\Platform;
use Throwable;

/**
 * Turns WHMCS orders into Moloni ON documents and manages created documents.
 *
 * The WHMCS order -> Moloni <Type>Insert mapping lives here. Customer, country
 * and product resolution are delegated to {@see CountryResolver} and
 * {@see ProductResolver} so those concerns can be refined independently.
 */
class DocumentService
{
    private MoloniClient $client;

    private SettingsService $settings;

    private CountryResolver $countries;

    private ProductResolver $products;

    private TaxResolver $taxes;

    public function __construct(MoloniClient $client, SettingsService $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
        $this->countries = new CountryResolver($client);
        $this->products = new ProductResolver($client, $settings);
        $this->taxes = new TaxResolver($client);
    }

    /**
     * Create a Moloni ON document from a WHMCS order.
     *
     * @return int The created Moloni document id.
     * @throws DocumentException
     */
    public function createDocumentFromOrder(int $orderId, ?string $documentType = null): int
    {
        $documentType = $documentType ?: $this->settings->documentType();

        if (!DocumentType::isValid($documentType)) {
            throw new ValidationException('Unsupported document type: ' . $documentType);
        }

        $order = Whmcs::getOrder($orderId);

        if ($order === null) {
            throw new DocumentException('WHMCS order not found.', ['order_id' => $orderId]);
        }

        try {
            $client = $order->userid ? Whmcs::getClient((int) $order->userid) : null;
            $customerId = $this->resolveCustomerId($client);

            $invoice = $order->invoiceid ? Whmcs::getInvoice((int) $order->invoiceid) : null;
            $items = $order->invoiceid ? Whmcs::getInvoiceItems((int) $order->invoiceid) : [];
            $fiscalZone = $this->fiscalZone();

            $payload = [
                'customerId' => $customerId,
                'documentSetId' => $this->settings->documentSetId(),
                'fiscalZone' => $fiscalZone['code'],
                'date' => date('Y-m-d H:i:s'),
                'status' => $this->settings->documentStatus(),
                'ourReference' => '#' . ($order->ordernum ?: $order->id),
                'yourReference' => '#' . ($order->ordernum ?: $order->id),
                'products' => $this->resolveProductLines($items, (float) $order->amount, $invoice, $fiscalZone),
            ];

            $result = $this->client->createDocument($payload, $documentType);
            $documentId = (int) ($result['documentId'] ?? 0);

            if ($documentId <= 0) {
                throw new DocumentException('Moloni ON did not return a document id.', ['order_id' => $orderId]);
            }

            $this->persist($order, $documentId, (float) ($result['documentTotal'] ?? $order->amount), $documentType);

            LoggerFacade::info('Document created.', [
                'order_id' => $orderId,
                'document_id' => $documentId,
            ], $orderId);

            return $documentId;
        } catch (ApiException $e) {
            Order::markFailed($orderId, $e->getMessage());
            LoggerFacade::error('Document creation failed.', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'data' => $e->getData(),
            ], $orderId);

            throw new DocumentException($e->getMessage(), $e->getData(), 0, $e);
        }
    }

    /**
     * Create documents for many orders, continuing past individual failures.
     *
     * @param array<int,int> $orderIds
     * @return array{created:array<int,int>,failed:array<int,array{order_id:int,error:string}>}
     */
    public function bulkCreateDocuments(array $orderIds, ?string $documentType = null): array
    {
        $created = [];
        $failed = [];

        foreach ($orderIds as $orderId) {
            $orderId = (int) $orderId;

            try {
                $created[] = $this->createDocumentFromOrder($orderId, $documentType);
            } catch (Throwable $e) {
                $failed[] = ['order_id' => $orderId, 'error' => $e->getMessage()];
            }
        }

        LoggerFacade::info('Bulk document creation finished.', [
            'created' => count($created),
            'failed' => count($failed),
        ]);

        return ['created' => $created, 'failed' => $failed];
    }

    /**
     * Fetch a document's live details from Moloni ON.
     *
     * @return array<string,mixed>
     * @throws DocumentException
     */
    public function getDocumentDetails(int $documentId): array
    {
        try {
            return $this->client->getDocument($documentId);
        } catch (ApiException $e) {
            throw new DocumentException($e->getMessage(), $e->getData(), 0, $e);
        }
    }

    /**
     * Fetch a document PDF for download (streamed, never cached).
     *
     * @return array{filename:string,content:string}
     * @throws DocumentException
     */
    public function downloadPdf(int $documentId): array
    {
        $documentType = Order::documentTypeFor((string) $documentId) ?: DocumentType::INVOICE;

        try {
            $token = $this->client->getDocumentPdfToken($documentId, $documentType);
        } catch (ApiException $e) {
            throw new DocumentException($e->getMessage(), $e->getData(), 0, $e);
        }

        if (empty($token['path']) || empty($token['token'])) {
            throw new DocumentException('Moloni ON did not return a PDF token.', ['document_id' => $documentId]);
        }

        $url = Platform::MEDIA_API_URL . $token['path'] . '?jwt=' . $token['token'];
        $content = $this->fetch($url);

        if ($content === '') {
            throw new DocumentException('Empty PDF received from Moloni ON.', ['document_id' => $documentId]);
        }

        return [
            'filename' => (string) ($token['filename'] ?? ('document-' . $documentId . '.pdf')),
            'content' => $content,
        ];
    }

    /**
     * Resolve (find or create) the Moloni customer for a WHMCS client.
     *
     * @param object|null $client tblclients row
     * @throws ApiException
     */
    private function resolveCustomerId($client): int
    {
        if ($client === null) {
            throw new DocumentException('Order has no associated WHMCS client.');
        }

        $vat = trim((string) ($client->tax_id ?? ''));

        if ($vat !== '') {
            $existing = $this->client->findCustomerByVat($vat);

            if ($existing !== null && !empty($existing['customerId'])) {
                return (int) $existing['customerId'];
            }
        }

        $country = $this->countries->resolve((string) ($client->country ?? ''));
        $name = trim((string) ($client->companyname ?: ($client->firstname . ' ' . $client->lastname)));

        $data = [
            'name' => $name !== '' ? $name : 'Customer',
            'email' => (string) ($client->email ?? ''),
            'vat' => $vat !== '' ? $vat : null,
            'address' => (string) ($client->address1 ?? ''),
            'city' => (string) ($client->city ?? ''),
            'zipCode' => (string) ($client->postcode ?? ''),
            'phone' => (string) ($client->phonenumber ?? ''),
            'contactName' => $name,
            'number' => $this->client->getCustomerNextNumber() ?? '',
        ];

        if ($country['countryId'] !== null) {
            $data['countryId'] = $country['countryId'];
        }

        if ($country['languageId'] !== null) {
            $data['languageId'] = $country['languageId'];
        }

        $created = $this->client->createCustomer($data);

        return (int) ($created['customerId'] ?? 0);
    }

    /**
     * Map WHMCS invoice line items to Moloni document product lines.
     *
     * Each line resolves (or creates) a Moloni product id and derives its taxes
     * from the WHMCS invoice's own VAT rate(s) — not from a fixed setting — so
     * the document reflects the tax actually charged on the order. Lines carry
     * productId + qty + price + taxes (or exemptionReason at 0%).
     *
     * @param array<int,object> $items tblinvoiceitems rows
     * @param object|null $invoice tblinvoices row (for taxrate/taxrate2)
     * @param array{code:string,countryId:int} $fiscalZone
     * @return array<int,array<string,mixed>>
     * @throws ApiException
     */
    private function resolveProductLines(array $items, float $orderTotal, $invoice, array $fiscalZone): array
    {
        $rates = $this->invoiceTaxRates($invoice);

        if ($items === []) {
            return [$this->buildLine('Order total', $orderTotal, $rates, $fiscalZone, 1, 'WHMCS-ORDER-TOTAL')];
        }

        $lines = [];
        $ordering = 1;

        foreach ($items as $item) {
            $name = (string) ($item->description ?? 'Item');
            $price = (float) ($item->amount ?? 0);
            // Only taxed lines get VAT; untaxed lines are exempt.
            $lineRates = ((int) ($item->taxed ?? 0)) === 1 ? $rates : [];

            $lines[] = $this->buildLine($name, $price, $lineRates, $fiscalZone, $ordering++);
        }

        return $lines;
    }

    /**
     * Build a single document product line, resolving product id and taxes.
     *
     * @param array<int,float> $rates Applicable VAT rates (percent).
     * @param array{code:string,countryId:int} $fiscalZone
     * @return array<string,mixed>
     * @throws ApiException
     */
    private function buildLine(
        string $name,
        float $price,
        array $rates,
        array $fiscalZone,
        int $ordering,
        ?string $reference = null
    ): array {
        $lineTaxes = [];
        $order = 1;

        foreach ($rates as $rate) {
            if ($rate <= 0) {
                continue;
            }

            $tax = $this->taxes->resolve($rate, $fiscalZone);

            if (!empty($tax['taxId'])) {
                $lineTaxes[] = [
                    'taxId' => (int) $tax['taxId'],
                    'value' => $rate,
                    'ordering' => $order++,
                    'cumulative' => false,
                ];
            }
        }

        $exemptionReason = $lineTaxes === [] ? $this->settings->exemptionReason() : '';

        return [
            'productId' => $this->products->resolveId($name, $price, $lineTaxes, $exemptionReason, $reference),
            'name' => $name,
            'qty' => 1,
            'price' => $price,
            'discount' => 0,
            'ordering' => $ordering,
            'taxes' => $lineTaxes,
            'exemptionReason' => $exemptionReason,
        ];
    }

    /**
     * The distinct positive VAT rates on a WHMCS invoice (taxrate, taxrate2).
     *
     * @param object|null $invoice tblinvoices row
     * @return array<int,float>
     */
    private function invoiceTaxRates($invoice): array
    {
        if ($invoice === null) {
            return [];
        }

        $rates = [];

        foreach (['taxrate', 'taxrate2'] as $field) {
            $rate = (float) ($invoice->$field ?? 0);

            if ($rate > 0 && !in_array($rate, $rates, true)) {
                $rates[] = $rate;
            }
        }

        return $rates;
    }

    /**
     * The active company's fiscal zone (code + countryId) from the Context.
     *
     * @return array{code:string,countryId:int}
     */
    private function fiscalZone(): array
    {
        $company = Context::$company;

        return [
            'code' => (string) ($company['fiscalZone']['fiscalZone'] ?? 'PT'),
            'countryId' => (int) ($company['country']['countryId'] ?? 0),
        ];
    }

    /**
     * @param object $order tblorders row
     */
    private function persist($order, int $documentId, float $documentTotal, string $documentType): void
    {
        Order::markSynced((int) $order->id, (string) $documentId, $documentType);

        Document::store([
            'order_id' => (int) $order->id,
            'order_total' => (float) $order->amount,
            'invoice_id' => $documentId,
            'invoice_date' => date('Y-m-d'),
            'invoice_status' => $this->settings->documentStatus(),
            'invoice_total' => $documentTotal,
            'value' => (float) $order->amount,
        ]);
    }

    private function fetch(string $url): string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => Platform::API_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $content = curl_exec($ch);
        curl_close($ch);

        return $content === false ? '' : (string) $content;
    }
}
