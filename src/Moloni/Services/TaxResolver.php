<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Enums\TaxFiscalZoneType;
use Moloni\Enums\TaxType;

/**
 * Resolves a Moloni ON VAT tax for a given rate within the company's fiscal
 * zone. Looks the tax up by rate + zone and creates it when missing, so the
 * document line carries the tax that matches the WHMCS order's actual rate
 * (rather than a fixed configured tax).
 */
class TaxResolver
{
    private MoloniClient $client;

    /** @var array<string,array<string,mixed>> rate|code => tax */
    private array $cache = [];

    public function __construct(MoloniClient $client)
    {
        $this->client = $client;
    }

    /**
     * Return the Moloni tax for a rate, creating it if necessary.
     *
     * @param array{code:string,countryId:int} $fiscalZone
     * @return array<string,mixed> The Moloni tax (contains taxId), or [] on failure.
     */
    public function resolve(float $rate, array $fiscalZone): array
    {
        $code = strtolower($fiscalZone['code'] ?? 'pt');
        $key = $rate . '|' . $code;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $tax = $this->client->findTax($rate, $code);

        if ($tax === null || empty($tax['taxId'])) {
            $tax = $this->create($rate, $fiscalZone);
        }

        return $this->cache[$key] = $tax;
    }

    /**
     * @param array{code:string,countryId:int} $fiscalZone
     * @return array<string,mixed>
     */
    private function create(float $rate, array $fiscalZone): array
    {
        $code = $fiscalZone['code'] ?? 'PT';

        return $this->client->createTax([
            'visible' => 1,
            'name' => 'VAT - ' . strtoupper($code) . ' - ' . $rate . '%',
            'fiscalZone' => $code,
            'countryId' => (int) ($fiscalZone['countryId'] ?? 0),
            'type' => TaxType::PERCENTAGE,
            'fiscalZoneFinanceType' => TaxFiscalZoneType::VAT,
            'isDefault' => false,
            'value' => $rate,
        ]);
    }
}
