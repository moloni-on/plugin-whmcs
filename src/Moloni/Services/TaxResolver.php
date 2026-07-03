<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Enums\TaxFiscalZoneType;
use Moloni\Enums\TaxType;
use Moloni\Support\FiscalZone;

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
     * @return array<string,mixed> The Moloni tax (contains taxId), or [] on failure.
     */
    public function resolve(float $rate, FiscalZone $fiscalZone): array
    {
        $code = $fiscalZone->code();
        $key = $rate . '|' . $code;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $tax = $this->client->findTax($rate, $code);

        if ($tax === null || empty($tax['taxId'])) {
            $tax = $this->create($rate, $code, $fiscalZone->countryId());
        }

        // Only memoise a real resolution: caching a failed lookup ([] / no
        // taxId) would silently strip VAT from every later line at this rate.
        if (!empty($tax['taxId'])) {
            $this->cache[$key] = $tax;
        }

        return $tax;
    }

    /**
     * @return array<string,mixed>
     */
    private function create(float $rate, string $code, int $countryId): array
    {
        return $this->client->createTax([
            'visible' => 1,
            'name' => 'VAT - ' . $code . ' - ' . $rate . '%',
            'fiscalZone' => $code,
            'countryId' => $countryId,
            'type' => TaxType::PERCENTAGE,
            'fiscalZoneFinanceType' => TaxFiscalZoneType::VAT,
            'isDefault' => false,
            'value' => $rate,
        ]);
    }
}
