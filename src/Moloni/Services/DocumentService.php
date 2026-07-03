<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Enums\DocumentStatus;
use Moloni\Enums\DocumentType;
use Moloni\Exceptions\ApiException;
use Moloni\Exceptions\DocumentException;
use Moloni\Exceptions\MoloniException;
use Moloni\Exceptions\SkippedException;
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
 * The WHMCS order -> Moloni <Type>Insert assembly lives here; the individual
 * concerns are delegated so each can be refined independently:
 * {@see CustomerResolver} (customer), {@see LineMapper} + {@see ProductResolver}
 * + {@see TaxResolver} (product lines) and {@see PaymentResolver} (payments).
 */
class DocumentService
{
    private MoloniClient $client;

    private SettingsService $settings;

    private CountryResolver $countries;

    private CustomerResolver $customers;

    private ProductResolver $products;

    private TaxResolver $taxes;

    private LineMapper $lines;

    private PaymentResolver $paymentResolver;

    public function __construct(MoloniClient $client, SettingsService $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
        $this->countries = new CountryResolver($client);
        $this->customers = new CustomerResolver($client, $settings, $this->countries);
        $this->products = new ProductResolver($client, $settings);
        $this->taxes = new TaxResolver($client);
        $this->lines = new LineMapper();
        $this->paymentResolver = new PaymentResolver($client);
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
            $invoice = $order->invoiceid ? Whmcs::getInvoice((int) $order->invoiceid) : null;
            $items = $order->invoiceid ? Whmcs::getInvoiceItems((int) $order->invoiceid) : [];

            // A WHMCS mass-payment invoice only bundles other invoices together;
            // those are billed on their own, so there is nothing to invoice here.
            if ($this->isMassPayment($items)) {
                Order::setStatus($orderId, Order::STATUS_DISCARDED);
                LoggerFacade::info(
                    'Order skipped: mass-payment invoice that only aggregates other invoices.',
                    ['order_id' => $orderId],
                    $orderId
                );

                throw new SkippedException('Mass-payment invoice; no document created.', ['order_id' => $orderId]);
            }

            $client = $order->userid ? Whmcs::getClient((int) $order->userid) : null;
            $customerId = $this->customers->resolve($client);

            $fiscalZone = $this->resolveFiscalZone($client);
            $reference = '#' . ($order->ordernum ?: $order->id);
            $now = date('Y-m-d H:i:s');
            $wantedStatus = $this->settings->documentStatus();

            $payload = [
                'customerId' => $customerId,
                'documentSetId' => $this->settings->documentSetId(),
                'fiscalZone' => $fiscalZone['code'],
                'date' => $now,
                'expirationDate' => $now,
                // Always create as draft; the document is only closed afterwards
                // once we've confirmed Moloni's total matches the order total.
                'status' => DocumentStatus::DRAFT,
                'ourReference' => $reference,
                'yourReference' => $reference,
                'products' => $this->resolveProductLines(
                    $items,
                    (float) $order->amount,
                    $invoice,
                    $fiscalZone,
                    (int) $order->invoiceid
                ),
            ];

            $payments = $this->resolvePayments($order, $documentType);

            if ($payments !== []) {
                $payload['payments'] = $payments;
            }

            $result = $this->client->createDocument($payload, $documentType);
            $documentId = (int) ($result['documentId'] ?? 0);

            if ($documentId <= 0) {
                throw new DocumentException('Moloni ON did not return a document id.', ['order_id' => $orderId]);
            }

            $orderTotal = (float) $order->amount;
            $documentTotal = (float) ($result['documentTotal'] ?? $orderTotal);
            $status = $this->closeIfRequested(
                $documentId,
                $documentType,
                $wantedStatus,
                $orderTotal,
                $documentTotal,
                $orderId
            );

            $this->persist($order, $documentId, $documentTotal, $documentType, $status);

            LoggerFacade::info('Document created.', [
                'order_id' => $orderId,
                'document_id' => $documentId,
                'status' => $status,
            ], $orderId);

            return $documentId;
        } catch (SkippedException $e) {
            // Not a failure: the order was intentionally skipped (and already
            // marked discarded + logged above). Let it propagate as-is.
            throw $e;
        } catch (Throwable $e) {
            // Any failure (API, validation, resolver, DB) must mark the order
            // failed and be logged with context — never a silent partial abort.
            $data = $e instanceof MoloniException ? $e->getData() : [];

            Order::markFailed($orderId, $e->getMessage());
            LoggerFacade::error('Document creation failed.', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'data' => $data,
            ], $orderId);

            throw new DocumentException($e->getMessage(), $data, 0, $e);
        }
    }

    /**
     * Create documents for many orders, continuing past individual failures.
     *
     * @param array<int,int> $orderIds
     * @return array{created:array<int,int>,skipped:array<int,int>,failed:array<int,array{order_id:int,error:string}>}
     */
    public function bulkCreateDocuments(array $orderIds, ?string $documentType = null): array
    {
        $created = [];
        $skipped = [];
        $failed = [];

        foreach ($orderIds as $orderId) {
            $orderId = (int) $orderId;

            try {
                $created[] = $this->createDocumentFromOrder($orderId, $documentType);
            } catch (SkippedException $e) {
                // Intentionally not billed (e.g. mass-payment invoice).
                $skipped[] = $orderId;
            } catch (Throwable $e) {
                $failed[] = ['order_id' => $orderId, 'error' => $e->getMessage()];
            }
        }

        LoggerFacade::info('Bulk document creation finished.', [
            'created' => count($created),
            'skipped' => count($skipped),
            'failed' => count($failed),
        ]);

        return ['created' => $created, 'skipped' => $skipped, 'failed' => $failed];
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
     * Whether an invoice is a WHMCS mass payment: it carries at least one
     * "Invoice" line (each referencing another invoice being paid) and no
     * billable product line of its own. Such an invoice must not be turned
     * into a Moloni document — the aggregated invoices are billed separately.
     *
     * @param array<int,object> $items tblinvoiceitems rows
     */
    private function isMassPayment(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        $hasMassPay = false;

        foreach ($items as $item) {
            if ((string) ($item->type ?? '') === 'Invoice') {
                $hasMassPay = true;
            } else {
                // A real, billable line: this is a normal invoice.
                return false;
            }
        }

        return $hasMassPay;
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
    private function resolveProductLines(
        array $items,
        float $orderTotal,
        $invoice,
        array $fiscalZone,
        int $invoiceId
    ): array {
        $rates = $this->invoiceTaxRates($invoice);

        if ($items === []) {
            // $orderTotal (tblorders.amount) is tax-inclusive, but Moloni adds
            // VAT on top of the line price, so send the net amount to avoid
            // double-taxing (mirrors the net per-item amounts below).
            $price = $this->netAmount($orderTotal, $rates);

            return [$this->buildLine('Order total', $price, $rates, $fiscalZone, 1, 'WHMCS-ORDER-TOTAL')];
        }

        $lines = [];
        $ordering = 1;

        foreach ($items as $item) {
            // Derive a stable reference, name, summary and discount from the
            // WHMCS line type; promotion lines are folded into the discounted
            // line and skipped here.
            $meta = $this->lines->map($item, $invoiceId);

            if ($meta['skip']) {
                continue;
            }

            $name = $meta['name'] !== '' ? $meta['name'] : (string) ($item->description ?? 'Item');
            $reference = $meta['reference'] !== '' ? $meta['reference'] : null;
            $price = (float) ($item->amount ?? 0);
            // Only taxed lines get VAT; untaxed lines are exempt.
            $lineRates = ((int) ($item->taxed ?? 0)) === 1 ? $rates : [];

            $lines[] = $this->buildLine(
                $name,
                $price,
                $lineRates,
                $fiscalZone,
                $ordering++,
                $reference,
                $meta['summary'],
                $meta['discount']
            );
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
        ?string $reference = null,
        string $summary = '',
        float $discount = 0.0
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
            'summary' => $summary,
            'qty' => 1,
            'price' => $price,
            'discount' => round($discount, 2),
            'ordering' => $ordering,
            'taxes' => $lineTaxes,
            'exemptionReason' => $exemptionReason,
        ];
    }

    /**
     * Payment entries for the order, or [] when the document type carries none
     * or the gateway cannot be resolved.
     *
     * @param object $order tblorders row
     * @return array<int,array<string,mixed>>
     * @throws ApiException
     */
    private function resolvePayments($order, string $documentType): array
    {
        if (!DocumentType::hasPayments($documentType)) {
            return [];
        }

        return $this->paymentResolver->resolve($order);
    }

    /**
     * Convert a tax-inclusive amount to its net value for the given VAT rates.
     * Line taxes are applied non-cumulatively, so the divisor is 1 + the sum
     * of the rates. Returns the amount unchanged when there is no tax.
     *
     * @param array<int,float> $rates
     */
    private function netAmount(float $gross, array $rates): float
    {
        $totalRate = 0.0;

        foreach ($rates as $rate) {
            if ($rate > 0) {
                $totalRate += $rate;
            }
        }

        return $totalRate > 0.0 ? $gross / (1 + $totalRate / 100) : $gross;
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
     * The document fiscal zone (code + countryId), honouring the
     * "fiscal zone based on" setting. When set to the client's billing country,
     * the zone follows that country; it falls back to the company zone whenever
     * the client has no usable country.
     *
     * @param object|null $client tblclients row
     * @return array{code:string,countryId:int}
     */
    private function resolveFiscalZone($client): array
    {
        $company = $this->companyFiscalZone();

        if ($this->settings->fiscalZoneBasedOn() !== SettingsService::FISCAL_ZONE_BILLING) {
            return $company;
        }

        $iso2 = strtoupper(trim((string) ($client->country ?? '')));

        if ($iso2 === '') {
            return $company;
        }

        // The fiscal zone code is the ISO-3166-1 alpha-2 country code; the
        // matching Moloni countryId comes from the country resolver.
        $country = $this->countries->resolve($iso2);

        return [
            'code' => $iso2,
            'countryId' => $country['countryId'] ?? $company['countryId'],
        ];
    }

    /**
     * The active company's fiscal zone (code + countryId) from the Context.
     *
     * @return array{code:string,countryId:int}
     */
    private function companyFiscalZone(): array
    {
        $company = Context::company();
        $fiscalZone = $company !== null ? $company->get('fiscalZone') : null;

        return [
            'code' => (string) ($fiscalZone['fiscalZone'] ?? 'PT'),
            'countryId' => $company !== null ? $company->getCountry() : 0,
        ];
    }

    /**
     * Close a freshly-created draft when — and only when — the operator asked
     * for a closed document AND Moloni's computed total matches the WHMCS order
     * total. A mismatch means the line mapping is off, so the document is left
     * as a draft for manual review rather than being locked as closed.
     *
     * @return int The document's final status (DRAFT or CLOSED).
     */
    private function closeIfRequested(
        int $documentId,
        string $documentType,
        int $wantedStatus,
        float $orderTotal,
        float $documentTotal,
        int $orderId
    ): int {
        if ($wantedStatus !== DocumentStatus::CLOSED) {
            return DocumentStatus::DRAFT;
        }

        if (!$this->totalsMatch($orderTotal, $documentTotal)) {
            LoggerFacade::warning('Document left as draft: totals do not match.', [
                'order_id' => $orderId,
                'document_id' => $documentId,
                'order_total' => $orderTotal,
                'document_total' => $documentTotal,
            ], $orderId);

            return DocumentStatus::DRAFT;
        }

        $this->client->updateDocumentStatus($documentId, DocumentStatus::CLOSED, $documentType);

        return DocumentStatus::CLOSED;
    }

    /**
     * Compare two monetary totals, tolerating floating-point rounding noise.
     */
    private function totalsMatch(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }

    /**
     * @param object $order tblorders row
     */
    private function persist($order, int $documentId, float $documentTotal, string $documentType, int $status): void
    {
        Order::markSynced((int) $order->id, (string) $documentId, $documentType);

        Document::store([
            'order_id' => (int) $order->id,
            'order_total' => (float) $order->amount,
            'invoice_id' => $documentId,
            'invoice_date' => date('Y-m-d'),
            'invoice_status' => $status,
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
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content === false) {
            throw new DocumentException('Could not download PDF from Moloni ON: ' . $error);
        }

        if ($status >= 400) {
            throw new DocumentException('Moloni ON media API returned HTTP ' . $status . '.');
        }

        return (string) $content;
    }
}
