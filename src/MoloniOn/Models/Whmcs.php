<?php

declare(strict_types=1);

namespace MoloniOn\Models;

use WHMCS\Database\Capsule;

/**
 * Read-only access to native WHMCS tables (orders, clients, invoices).
 *
 * Kept separate from the module's own models so all WHMCS schema knowledge
 * lives in one place.
 */
class Whmcs
{
    /**
     * @return object|null
     */
    public static function getOrder(int $orderId)
    {
        return Capsule::table('tblorders')->where('id', $orderId)->first();
    }

    /**
     * @return object|null
     */
    public static function getClient(int $userId)
    {
        return Capsule::table('tblclients')->where('id', $userId)->first();
    }

    /**
     * Order number + client currency for a set of order ids, keyed by order id.
     * Used to enrich stored documents (which only keep the order id) for display.
     *
     * @param array<int,int> $orderIds
     * @return array<int,object>
     */
    public static function orderMetaByIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = Capsule::table('tblorders')
            ->leftJoin('tblclients', 'tblorders.userid', '=', 'tblclients.id')
            ->leftJoin('tblcurrencies', 'tblclients.currency', '=', 'tblcurrencies.id')
            ->whereIn('tblorders.id', $orderIds)
            ->get([
                'tblorders.id',
                'tblorders.ordernum',
                'tblcurrencies.code as currency_code',
                'tblcurrencies.prefix as currency_prefix',
                'tblcurrencies.suffix as currency_suffix',
            ]);

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->id] = $row;
        }

        return $map;
    }

    /**
     * Line items for the invoice attached to an order.
     *
     * @return array<int,object>
     */
    public static function getInvoiceItems(int $invoiceId): array
    {
        return Capsule::table('tblinvoiceitems')
            ->where('invoiceid', $invoiceId)
            ->get()
            ->all();
    }

    /**
     * @return object|null
     */
    public static function getInvoice(int $invoiceId)
    {
        return Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    }

    /**
     * @return object|null
     */
    public static function getOrderByInvoice(int $invoiceId)
    {
        return Capsule::table('tblorders')->where('invoiceid', $invoiceId)->first();
    }

    public static function currencyCode(int $currencyId): ?string
    {
        $row = Capsule::table('tblcurrencies')->where('id', $currencyId)->first();

        return $row->code ?? null;
    }

    /**
     * Domain (tbldomains) row for a domain invoice line's relid.
     *
     * @return object|null
     */
    public static function getDomainInfo(int $domainId)
    {
        return Capsule::table('tbldomains')->where('id', $domainId)->first();
    }

    /**
     * Hosting service joined with its product, for a hosting line's relid.
     *
     * @return object|null
     */
    public static function getHostingInfo(int $hostingId)
    {
        return Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblhosting.id', $hostingId)
            ->select('tblhosting.*', 'tblproducts.name')
            ->first();
    }

    /**
     * Hosting addon joined with its addon definition, for an addon line's relid.
     *
     * @return object|null
     */
    public static function getAddonInfo(int $addonId)
    {
        return Capsule::table('tblhostingaddons')
            ->join('tbladdons', 'tblhostingaddons.addonid', '=', 'tbladdons.id')
            ->join('tblhosting', 'tblhostingaddons.hostingid', '=', 'tblhosting.id')
            ->where('tblhostingaddons.id', $addonId)
            ->select('tblhostingaddons.*', 'tbladdons.name', 'tblhosting.domain', 'tblhosting.nextduedate')
            ->first();
    }

    /**
     * Upgrade joined with the hosting service and its product, for an
     * upgrade line's relid.
     *
     * @return object|null
     */
    public static function getUpgradeInfo(int $upgradeId)
    {
        return Capsule::table('tblupgrades')
            ->join('tblhosting', 'tblhosting.id', '=', 'tblupgrades.relid')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblupgrades.id', $upgradeId)
            ->select('tblupgrades.*', 'tblproducts.name')
            ->first();
    }

    /**
     * Value of a named client custom field (tblcustomfields/tblcustomfieldsvalues),
     * e.g. a VAT/NIF field. Returns null when unset or empty.
     */
    public static function getClientCustomFieldValue(int $userId, string $fieldName): ?string
    {
        if ($fieldName === '') {
            return null;
        }

        $row = Capsule::table('tblcustomfieldsvalues')
            ->join('tblcustomfields', 'tblcustomfields.id', '=', 'tblcustomfieldsvalues.fieldid')
            ->where('tblcustomfields.type', 'client')
            ->where('tblcustomfields.fieldname', $fieldName)
            ->where('tblcustomfieldsvalues.relid', $userId)
            ->select('tblcustomfieldsvalues.value')
            ->first();

        $value = isset($row->value) ? trim((string) $row->value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * Distinct product custom-field names, used to populate the "custom
     * reference" setting dropdown.
     *
     * @return array<int,string>
     */
    public static function productCustomFieldNames(): array
    {
        return Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->whereNotNull('fieldname')
            ->where('fieldname', '!=', '')
            ->distinct()
            ->orderBy('fieldname')
            ->pluck('fieldname')
            ->all();
    }

    /**
     * The description text of a product's custom field (matched by field name),
     * used as that product's Moloni reference. Mirrors the classic Moloni WHMCS
     * plugin, which stored the reference in the field's description.
     */
    public static function productCustomFieldDescription(int $packageId, string $fieldName): ?string
    {
        if ($fieldName === '' || $packageId <= 0) {
            return null;
        }

        $row = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', $fieldName)
            ->where('relid', $packageId)
            ->first(['description']);

        $value = isset($row->description) ? trim((string) $row->description) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * Promotion amount applied to an invoice line, as a positive number.
     *
     * WHMCS records promotions as separate negative line items ("PromoDomain"
     * or "PromoHosting") pointing at the same relid as the discounted line.
     */
    public static function getLineDiscountAmount(int $invoiceId, int $relId, string $promoType): float
    {
        $row = Capsule::table('tblinvoiceitems')
            ->where('invoiceid', $invoiceId)
            ->where('type', $promoType)
            ->where('relid', $relId)
            ->first();

        $amount = isset($row->amount) ? (float) $row->amount : 0.0;

        return $amount < 0 ? abs($amount) : 0.0;
    }

    /**
     * Display name of a WHMCS payment gateway (tblpaymentgateways `name`
     * setting), used to label the Moloni payment method. Null when unknown.
     */
    public static function getGatewayName(string $gateway): ?string
    {
        if ($gateway === '') {
            return null;
        }

        $row = Capsule::table('tblpaymentgateways')
            ->where('gateway', $gateway)
            ->where('setting', 'name')
            ->first();

        $value = isset($row->value) ? trim((string) $row->value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * Orders joined with their client, most recent first.
     *
     * When $paidOnly is true, only orders whose WHMCS invoice is "Paid" are
     * returned (matching the classic plugin, which listed Paid invoices only).
     *
     * @return array<int,object> rows with order + client_firstname/lastname/email/companyname
     */
    public static function ordersWithClients(int $limit = 500, bool $paidOnly = false): array
    {
        $query = Capsule::table('tblorders')
            ->leftJoin('tblclients', 'tblorders.userid', '=', 'tblclients.id')
            ->leftJoin('tblcurrencies', 'tblclients.currency', '=', 'tblcurrencies.id');

        if ($paidOnly) {
            $query->join('tblinvoices', 'tblorders.invoiceid', '=', 'tblinvoices.id')
                ->where('tblinvoices.status', 'Paid');
        }

        return $query
            ->orderByDesc('tblorders.date')
            ->limit($limit)
            ->get([
                'tblorders.id',
                'tblorders.ordernum',
                'tblorders.userid',
                'tblorders.invoiceid',
                'tblorders.amount',
                'tblorders.date',
                'tblorders.status',
                'tblorders.paymentmethod',
                'tblclients.firstname as client_firstname',
                'tblclients.lastname as client_lastname',
                'tblclients.companyname as client_companyname',
                'tblclients.email as client_email',
                'tblcurrencies.code as currency_code',
                'tblcurrencies.prefix as currency_prefix',
                'tblcurrencies.suffix as currency_suffix',
            ])
            ->all();
    }
}
