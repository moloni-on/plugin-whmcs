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
use Moloni\Support\CurrencyExchange;
use Moloni\Support\FiscalZone;
use Moloni\Support\Hooks;
use Moloni\Support\LineInput;
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
    /** Largest difference (in currency units) still treated as an exact total match. */
    private const MONETARY_EPSILON = 0.01;

    /** Fallback name for an invoice item that carries neither a mapped name nor a description. */
    private const FALLBACK_LINE_NAME = 'Item';

    /** How many times to poll for a freshly-exported PDF token before giving up. */
    private const PDF_EXPORT_POLL_ATTEMPTS = 3;

    /** Seconds to wait between PDF token polls (export is asynchronous). */
    private const PDF_EXPORT_POLL_WAIT_SECONDS = 2;

    private MoloniClient $client;

    private SettingsService $settings;

    private CountryResolver $countries;

    private CustomerResolver $customers;

    private ProductResolver $products;

    private TaxResolver $taxes;

    private LineMapper $lines;

    private PaymentResolver $paymentResolver;

    private CurrencyResolver $currency;

    public function __construct(MoloniClient $client, SettingsService $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
        $this->countries = new CountryResolver($client);
        $this->customers = new CustomerResolver($client, $settings, $this->countries);
        $this->products = new ProductResolver($client, $settings);
        $this->taxes = new TaxResolver($client);
        $this->lines = new LineMapper($settings);
        $this->paymentResolver = new PaymentResolver($client, $settings);
        $this->currency = new CurrencyResolver($client);
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

            $this->guardAgainstNoItems($orderId, $items);
            $this->guardAgainstMassPayment($orderId, $items);

            $payload = $this->buildDocumentPayload($order, $invoice, $items, $documentType);

            // Let integrators inspect or amend the <Type>Insert before it is sent.
            $payload = Hooks::filter(Hooks::BEFORE_CREATE_DOCUMENT, $payload, [
                'order_id' => $orderId,
                'document_type' => $documentType,
                'order' => $order,
            ]);

            $document = $this->submitAndReconcile($payload, $order, $documentType);

            $this->persist($order, $document['id'], $document['total'], $documentType, $document['status']);

            LoggerFacade::info('Document created.', [
                'order_id' => $orderId,
                'document_id' => $document['id'],
                'status' => $document['status'],
            ], $orderId);

            Hooks::doAction(Hooks::AFTER_CREATE_DOCUMENT, [
                'order_id' => $orderId,
                'document_id' => $document['id'],
                'document_type' => $documentType,
                'total' => $document['total'],
                'status' => $document['status'],
            ]);

            return $document['id'];
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

            Hooks::doAction(Hooks::DOCUMENT_FAILED, [
                'order_id' => $orderId,
                'document_type' => $documentType,
                'error' => $e->getMessage(),
            ]);

            throw new DocumentException($e->getMessage(), $data, 0, $e);
        }
    }

    /**
     * A document must have at least one billable line. An order with no invoice
     * (or an invoice with no items) cannot be turned into a Moloni document, so
     * it is refused — mirroring the classic Moloni WHMCS plugin, which required
     * invoice items.
     *
     * @param array<int,object> $items tblinvoiceitems rows
     * @throws DocumentException when the order has no invoice items
     */
    private function guardAgainstNoItems(int $orderId, array $items): void
    {
        if ($items !== []) {
            return;
        }

        throw new DocumentException('Order has no invoice items to bill.', ['order_id' => $orderId]);
    }

    /**
     * A WHMCS mass-payment invoice only bundles other invoices together; those
     * are billed on their own, so there is nothing to invoice here. Mark the
     * order discarded and skip it.
     *
     * @param array<int,object> $items tblinvoiceitems rows
     * @throws SkippedException when the invoice is a mass payment
     */
    private function guardAgainstMassPayment(int $orderId, array $items): void
    {
        if (!$this->isMassPayment($items)) {
            return;
        }

        Order::setStatus($orderId, Order::STATUS_DISCARDED);
        LoggerFacade::info(
            'Order skipped: mass-payment invoice that only aggregates other invoices.',
            ['order_id' => $orderId],
            $orderId
        );

        throw new SkippedException('Mass-payment invoice; no document created.', ['order_id' => $orderId]);
    }

    /**
     * Assemble the `<Type>Insert` payload for a WHMCS order: customer, fiscal
     * zone, references, product lines and (when the type carries them) payments.
     *
     * @param object $order tblorders row
     * @param object|null $invoice tblinvoices row
     * @param array<int,object> $items tblinvoiceitems rows
     * @return array<string,mixed>
     * @throws ApiException
     */
    private function buildDocumentPayload($order, $invoice, array $items, string $documentType): array
    {
        $client = $order->userid ? Whmcs::getClient((int) $order->userid) : null;
        $fiscalZone = $this->resolveFiscalZone($client);
        $exchange = $this->currency->resolve($client);
        $now = date('Y-m-d H:i:s');

        $payload = [
            'customerId' => $this->customers->resolve($client),
            'documentSetId' => $this->settings->documentSetId(),
            'fiscalZone' => $fiscalZone->code(),
            'date' => $now,
            'expirationDate' => $now,
            // Always create as draft; the document is only closed afterwards
            // once we've confirmed Moloni's total matches the order total.
            'status' => DocumentStatus::DRAFT,
            // Mirrors the classic plugin: our reference is the WHMCS invoice id,
            // your reference the WHMCS invoice number, falling back to the order
            // number so the document always carries a human reference.
            'ourReference' => (string) ($invoice->id ?? $order->id),
            'yourReference' => $this->yourReference($order, $invoice),
            'products' => $this->resolveProductLines(
                $items,
                $invoice,
                $fiscalZone,
                (int) $order->invoiceid,
                $exchange
            ),
        ];

        if ($exchange !== null) {
            $payload['currencyExchangeId'] = $exchange->id();
            $payload['currencyExchangeExchange'] = $exchange->rate();
        }

        $payments = $this->resolvePayments($order, $documentType, $exchange);

        if ($payments !== []) {
            $payload['payments'] = $payments;
        }

        return $payload;
    }

    /**
     * The document's "your reference": the WHMCS invoice number when present,
     * otherwise the order number (WHMCS default numbering leaves invoicenum
     * empty), so the field is never blank.
     *
     * @param object $order tblorders row
     * @param object|null $invoice tblinvoices row
     */
    private function yourReference($order, $invoice): string
    {
        $invoiceNumber = trim((string) ($invoice->invoicenum ?? ''));

        if ($invoiceNumber !== '') {
            return $invoiceNumber;
        }

        return '#' . ($order->ordernum ?: $order->id);
    }

    /**
     * Create the document in Moloni ON, then close it when the totals match the
     * order (see {@see closeIfRequested()}).
     *
     * @param array<string,mixed> $payload
     * @param object $order tblorders row
     * @return array{id:int,total:float,status:int}
     * @throws ApiException|DocumentException
     */
    private function submitAndReconcile(array $payload, $order, string $documentType): array
    {
        $orderId = (int) $order->id;
        $result = $this->client->createDocument($payload, $documentType);
        $documentId = (int) ($result['documentId'] ?? 0);

        if ($documentId <= 0) {
            throw new DocumentException('Moloni ON did not return a document id.', ['order_id' => $orderId]);
        }

        $orderTotal = (float) $order->amount;
        $documentTotal = (float) ($result['documentTotal'] ?? $orderTotal);

        // The WHMCS order total is in the client currency, whereas Moloni stores
        // documentTotal in the company base currency. For a foreign-currency
        // document Moloni also returns the total converted back to the client
        // currency (currencyExchangeTotalValue = exchange * totalValue); it is 0
        // for same-currency documents. Reconcile — and store/display — in the
        // client currency so the figure lines up with the WHMCS order total.
        $exchangeTotal = (float) ($result['currencyExchangeTotalValue'] ?? 0);
        $clientCurrencyTotal = $exchangeTotal > 0.0 ? $exchangeTotal : $documentTotal;
        $status = $this->closeIfRequested($documentId, $documentType, $orderTotal, $clientCurrencyTotal, $orderId);

        // A document is only e-mailed once it is actually closed (a draft has no
        // final number to send).
        if ($status === DocumentStatus::CLOSED) {
            $this->sendEmailIfRequested($documentId, $documentType, $order);
        }

        return ['id' => $documentId, 'total' => $clientCurrencyTotal, 'status' => $status];
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
        try {
            $document = $this->client->getDocument($documentId);
            $documentType = $this->resolveDocumentType($document, $documentId);

            // The PDF token query is rejected until the PDF has been exported, so
            // generate it first when Moloni reports none yet. Generation is
            // asynchronous, so poll for the token instead of a single blind wait.
            if (empty($document['pdfExport'])) {
                $this->client->createDocumentPdf($documentId, $documentType);
                $token = $this->pollForPdfToken($documentId, $documentType);
            } else {
                $token = $this->client->getDocumentPdfToken($documentId, $documentType);
            }
        } catch (ApiException $e) {
            throw new DocumentException($e->getMessage(), $e->getData(), 0, $e);
        }

        if (empty($token['path']) || empty($token['token'])) {
            throw new DocumentException('Moloni ON did not return a PDF token.', ['document_id' => $documentId]);
        }

        $url = Platform::MEDIA_API_URL . $token['path'] . '?jwt=' . $token['token'];

        try {
            $content = $this->client->downloadMedia($url);
        } catch (ApiException $e) {
            throw new DocumentException($e->getMessage(), $e->getData(), 0, $e);
        }

        if ($content === '') {
            throw new DocumentException('Empty PDF received from Moloni ON.', ['document_id' => $documentId]);
        }

        return [
            'filename' => (string) ($token['filename'] ?? ('document-' . $documentId . '.pdf')),
            'content' => $content,
        ];
    }

    /**
     * Poll Moloni ON for a freshly-exported document's PDF token. The export is
     * asynchronous, so the token query can be rejected or return empty for a
     * short while after triggering it; retry a bounded number of times.
     *
     * @return array<string,mixed> the token payload, or [] if none was produced
     * @throws ApiException if the token query kept failing across all attempts
     */
    private function pollForPdfToken(int $documentId, string $documentType): array
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::PDF_EXPORT_POLL_ATTEMPTS; $attempt++) {
            sleep(self::PDF_EXPORT_POLL_WAIT_SECONDS);

            try {
                $token = $this->client->getDocumentPdfToken($documentId, $documentType);
            } catch (ApiException $e) {
                $lastError = $e;
                continue;
            }

            if (!empty($token['path']) && !empty($token['token'])) {
                return $token;
            }
        }

        if ($lastError !== null) {
            throw $lastError;
        }

        return [];
    }

    /**
     * Build the Moloni ON web URL to view a document, e.g.
     * `https://ac.molonion.pt/<slug>/invoices/view/<id>`.
     *
     * @throws DocumentException
     */
    public function documentViewUrl(int $documentId): string
    {
        try {
            $document = $this->client->getDocument($documentId);
        } catch (ApiException $e) {
            throw new DocumentException($e->getMessage(), $e->getData(), 0, $e);
        }

        $slug = trim((string) ($document['company']['slug'] ?? ''));
        $plural = trim((string) ($document['documentType']['apiCodePlural'] ?? ''));

        if ($slug === '' || $plural === '') {
            throw new DocumentException('Moloni ON did not return the document location.', [
                'document_id' => $documentId,
            ]);
        }

        return Platform::AC_URL . $slug . '/' . $plural . '/view/' . $documentId;
    }

    /**
     * Resolve a document's Moloni type: its live apiCode, falling back to the
     * type stored on the order, then to a plain invoice. The returned value uses
     * the same vocabulary as {@see DocumentType} (e.g. "invoice"), so it is safe
     * to feed straight into the `<type>GetPDF`/`<type>GetPDFToken` operations.
     *
     * @param array<string,mixed> $document
     */
    private function resolveDocumentType(array $document, int $documentId): string
    {
        $apiCode = trim((string) ($document['documentType']['apiCode'] ?? ''));

        if ($apiCode !== '') {
            return $apiCode;
        }

        return Order::documentTypeFor((string) $documentId) ?: DocumentType::INVOICE;
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
     * @param CurrencyExchange|null $exchange Applied to line prices when the
     *        order currency differs from the company base currency.
     * @return array<int,array<string,mixed>>
     * @throws ApiException
     */
    private function resolveProductLines(
        array $items,
        $invoice,
        FiscalZone $fiscalZone,
        int $invoiceId,
        ?CurrencyExchange $exchange
    ): array {
        $rates = $this->invoiceTaxRates($invoice);
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

            // WHMCS line amounts are in the client currency; convert to the
            // company base currency when an exchange applies.
            $amount = (float) ($item->amount ?? 0);
            $price = $exchange !== null ? $exchange->toBase($amount) : $amount;

            $line = new LineInput(
                $meta['name'] !== '' ? $meta['name'] : (string) ($item->description ?? self::FALLBACK_LINE_NAME),
                $price,
                $meta['reference'] !== '' ? $meta['reference'] : null,
                $meta['summary'],
                $meta['discount'],
                $meta['productName']
            );
            // Only taxed lines get VAT; untaxed lines are exempt.
            $lineRates = ((int) ($item->taxed ?? 0)) === 1 ? $rates : [];

            $lines[] = $this->buildLine($line, $lineRates, $fiscalZone, $ordering++);
        }

        return $lines;
    }

    /**
     * Build a single document product line, resolving product id and taxes.
     *
     * @param array<int,float> $rates Applicable VAT rates (percent).
     * @return array<string,mixed>
     * @throws ApiException
     */
    private function buildLine(LineInput $line, array $rates, FiscalZone $fiscalZone, int $ordering): array
    {
        $lineTaxes = [];
        $taxOrdering = 1;

        foreach ($rates as $rate) {
            if ($rate <= 0) {
                continue;
            }

            $tax = $this->taxes->resolve($rate, $fiscalZone);

            if (!empty($tax['taxId'])) {
                $lineTaxes[] = [
                    'taxId' => (int) $tax['taxId'],
                    'value' => $rate,
                    'ordering' => $taxOrdering++,
                    'cumulative' => false,
                ];
            }
        }

        // A line that resolves to no VAT is automatically tax-exempt and carries
        // the configured exemption reason; taxed lines never carry one.
        $exemptionReason = $lineTaxes === [] ? $this->settings->exemptionReason() : '';

        return [
            'productId' => $this->products->resolveId(
                $line->name(),
                $line->price(),
                $lineTaxes,
                $exemptionReason,
                $line->reference(),
                $line->productName()
            ),
            'name' => $line->name(),
            'summary' => $line->summary(),
            'qty' => 1,
            'price' => $line->price(),
            'discount' => round($line->discount(), 2),
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
    private function resolvePayments($order, string $documentType, ?CurrencyExchange $exchange): array
    {
        if (!DocumentType::hasPayments($documentType)) {
            return [];
        }

        return $this->paymentResolver->resolve($order, $exchange);
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
     */
    private function resolveFiscalZone($client): FiscalZone
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

        return new FiscalZone($iso2, (int) ($country['countryId'] ?? $company->countryId()));
    }

    /**
     * The active company's fiscal zone from the Context.
     */
    private function companyFiscalZone(): FiscalZone
    {
        $company = Context::company();
        $fiscalZone = $company !== null ? $company->get('fiscalZone') : null;

        return new FiscalZone(
            (string) ($fiscalZone['fiscalZone'] ?? ''),
            $company !== null ? $company->getCountry() : 0
        );
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
        float $orderTotal,
        float $documentTotal,
        int $orderId
    ): int {
        if ($this->settings->documentStatus() !== DocumentStatus::CLOSED) {
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

        // Let integrators keep a matched document as a draft (return false).
        $mayClose = Hooks::allows(Hooks::BEFORE_CLOSE_DOCUMENT, [
            'order_id' => $orderId,
            'document_id' => $documentId,
            'document_type' => $documentType,
            'order_total' => $orderTotal,
            'document_total' => $documentTotal,
        ]);

        if (!$mayClose) {
            LoggerFacade::info('Document left as draft: closing vetoed by hook.', [
                'order_id' => $orderId,
                'document_id' => $documentId,
            ], $orderId);

            return DocumentStatus::DRAFT;
        }

        $this->client->updateDocumentStatus($documentId, DocumentStatus::CLOSED, $documentType);

        Hooks::doAction(Hooks::AFTER_CLOSE_DOCUMENT, [
            'order_id' => $orderId,
            'document_id' => $documentId,
            'document_type' => $documentType,
        ]);

        return DocumentStatus::CLOSED;
    }

    /**
     * E-mail the closed document to the order's customer when the setting is on.
     *
     * Never fatal: the document already exists and is closed, so a mail failure
     * (or a customer with no e-mail) is logged and swallowed rather than marking
     * the whole order failed.
     *
     * @param object $order tblorders row
     */
    private function sendEmailIfRequested(int $documentId, string $documentType, $order): void
    {
        if (!$this->settings->sendEmail()) {
            return;
        }

        $orderId = (int) $order->id;
        $client = $order->userid ? Whmcs::getClient((int) $order->userid) : null;
        $email = trim((string) ($client->email ?? ''));

        if ($email === '') {
            LoggerFacade::warning('Document not e-mailed: customer has no e-mail address.', [
                'order_id' => $orderId,
                'document_id' => $documentId,
            ], $orderId);

            return;
        }

        try {
            $this->client->sendDocumentMail($documentId, $this->customerName($client), $email, $documentType);
            LoggerFacade::info('Document e-mailed to customer.', [
                'order_id' => $orderId,
                'document_id' => $documentId,
            ], $orderId);
        } catch (ApiException $e) {
            LoggerFacade::error('Failed to e-mail document.', [
                'order_id' => $orderId,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ], $orderId);
        }
    }

    /**
     * Recipient name for a document e-mail: company name, else contact name,
     * else a neutral fallback.
     *
     * @param object|null $client tblclients row
     */
    private function customerName($client): string
    {
        $name = trim((string) ($client->companyname ?? ''));

        if ($name === '') {
            $name = trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? ''));
        }

        return $name !== '' ? $name : 'Customer';
    }

    /**
     * Compare two monetary totals, tolerating floating-point rounding noise.
     */
    private function totalsMatch(float $orderTotal, float $documentTotal): bool
    {
        return abs($orderTotal - $documentTotal) < self::MONETARY_EPSILON;
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
}
