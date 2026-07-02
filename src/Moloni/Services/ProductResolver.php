<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Enums\ProductType;
use Moloni\Enums\ProductTypeAT;

/**
 * Resolves a Moloni ON product id for a WHMCS line item.
 *
 * Invoice lines require an existing productId, so each item is matched by a
 * deterministic reference (products filter) and created on demand when missing.
 * The ProductInsert field set mirrors the Moloni ON WooCommerce plugin.
 */
class ProductResolver
{
    private MoloniClient $client;

    private SettingsService $settings;

    /** @var array<string,int> reference => productId (per-request cache) */
    private array $cache = [];

    public function __construct(MoloniClient $client, SettingsService $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
    }

    /**
     * Return the Moloni product id for the given item, creating it if needed.
     *
     * @param array<int,array<string,mixed>> $taxes  Product taxes ({taxId,value,ordering}).
     * @throws \Moloni\Exceptions\ApiException
     */
    public function resolveId(
        string $name,
        float $price,
        array $taxes = [],
        string $exemptionReason = '',
        ?string $reference = null
    ): int {
        $name = $name !== '' ? $name : 'Item';
        $reference = $reference ?: $this->referenceFrom($name);

        if (isset($this->cache[$reference])) {
            return $this->cache[$reference];
        }

        $existing = $this->client->findProductByReference($reference);

        if ($existing !== null && !empty($existing['productId'])) {
            return $this->cache[$reference] = (int) $existing['productId'];
        }

        $insert = $this->buildInsert($name, $reference, $price, $taxes, $exemptionReason);
        $created = $this->client->createProduct($insert);

        return $this->cache[$reference] = (int) ($created['productId'] ?? 0);
    }

    /**
     * @param array<int,array<string,mixed>> $taxes
     * @return array<string,mixed>
     */
    private function buildInsert(
        string $name,
        string $reference,
        float $price,
        array $taxes,
        string $exemptionReason
    ): array {
        $data = [
            'visible' => 1,
            'name' => $name,
            'reference' => $reference,
            'summary' => '',
            'price' => $price,
            'type' => ProductType::SERVICE,
            'productAT' => ['productType' => ProductTypeAT::GOODS],
            'hasStock' => false,
        ];

        if ($this->settings->measurementUnitId() > 0) {
            $data['measurementUnitId'] = $this->settings->measurementUnitId();
        }

        if ($this->settings->productCategoryId() > 0) {
            $data['productCategoryId'] = $this->settings->productCategoryId();
        }

        if ($taxes !== []) {
            // Product taxes carry {taxId, value, ordering} (no `cumulative`).
            $data['taxes'] = array_map(
                static fn (array $tax): array => [
                    'taxId' => $tax['taxId'],
                    'value' => $tax['value'],
                    'ordering' => $tax['ordering'],
                ],
                $taxes
            );
        } elseif ($exemptionReason !== '') {
            $data['exemptionReason'] = $exemptionReason;
        }

        return $data;
    }

    private function referenceFrom(string $name): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');

        return 'WHMCS-' . substr($slug !== '' ? $slug : md5($name), 0, 40);
    }
}
