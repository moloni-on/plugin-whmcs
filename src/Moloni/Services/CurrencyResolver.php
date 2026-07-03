<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Exceptions\ApiException;
use Moloni\Exceptions\DocumentException;
use Moloni\Models\Whmcs;
use Moloni\Support\Context;
use Moloni\Support\CurrencyExchange;

/**
 * Resolves the currency exchange to apply to a document.
 *
 * WHMCS keeps order amounts in the client's currency, while Moloni ON stores
 * every document in the company's base currency. When the two differ, the
 * document must carry a currency exchange (id + rate) and its amounts are
 * converted back to the base currency (see {@see CurrencyExchange::toBase()}).
 * Mirrors the Moloni ON WooCommerce plugin's exchange handling.
 */
class CurrencyResolver
{
    private MoloniClient $client;

    public function __construct(MoloniClient $client)
    {
        $this->client = $client;
    }

    /**
     * The exchange for a WHMCS client's currency, or null when it matches the
     * company's base currency (no conversion needed).
     *
     * @param object|null $whmcsClient tblclients row
     * @throws ApiException
     * @throws DocumentException when the client currency has no exchange in Moloni
     */
    public function resolve($whmcsClient): ?CurrencyExchange
    {
        $company = Context::company();
        $base = $company !== null ? $company->getCurrencyCode() : '';
        $order = $this->clientCurrencyCode($whmcsClient);

        if ($base === '' || $order === '' || $base === $order) {
            return null;
        }

        $exchange = $this->client->findCurrencyExchange($base, $order) ?? [];
        $rate = (float) ($exchange['exchange'] ?? 0);
        $id = (int) ($exchange['currencyExchangeId'] ?? 0);

        if ($id <= 0 || $rate <= 0.0) {
            throw new DocumentException('No Moloni ON exchange rate found for the order currency.', [
                'base_currency' => $base,
                'order_currency' => $order,
            ]);
        }

        return new CurrencyExchange($id, $rate);
    }

    /**
     * The ISO-4217 code of a WHMCS client's currency, upper-cased, or "".
     *
     * @param object|null $whmcsClient tblclients row
     */
    private function clientCurrencyCode($whmcsClient): string
    {
        $currencyId = (int) ($whmcsClient->currency ?? 0);

        if ($currencyId <= 0) {
            return '';
        }

        return strtoupper(trim((string) Whmcs::currencyCode($currencyId)));
    }
}
