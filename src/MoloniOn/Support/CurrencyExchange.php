<?php

declare(strict_types=1);

namespace MoloniOn\Support;

/**
 * A resolved Moloni ON currency exchange: the exchange id to stamp on the
 * document plus the rate used to convert order-currency amounts back to the
 * company's base currency.
 *
 * Moloni stores every document in the company's base currency, so line prices
 * and payment values (which WHMCS keeps in the client's currency) are divided
 * by the base -> order rate before they are sent, mirroring the Moloni ON
 * WooCommerce plugin.
 */
final class CurrencyExchange
{
    private int $id;

    private float $rate;

    public function __construct(int $id, float $rate)
    {
        $this->id = $id;
        $this->rate = $rate;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function rate(): float
    {
        return $this->rate;
    }

    /**
     * Convert an amount from the order currency to the company base currency.
     */
    public function toBase(float $amount): float
    {
        return $this->rate > 0.0 ? $amount / $this->rate : $amount;
    }
}
