<?php

declare(strict_types=1);

namespace Moloni\Models;

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
     * Orders joined with their client, most recent first.
     *
     * @return array<int,object> rows with order + client_firstname/lastname/email/companyname
     */
    public static function ordersWithClients(int $limit = 500): array
    {
        return Capsule::table('tblorders')
            ->leftJoin('tblclients', 'tblorders.userid', '=', 'tblclients.id')
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
            ])
            ->all();
    }
}
