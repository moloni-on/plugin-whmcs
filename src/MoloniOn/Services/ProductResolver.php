<?php

declare(strict_types=1);

namespace MoloniOn\Services;

use MoloniOn\Api\MoloniClient;
use MoloniOn\Enums\ProductType;
use MoloniOn\Exceptions\ApiException;

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
     * @param string|null $createName Generic name to use when the product is
     *        created (permanent); falls back to $name. Does not affect matching.
     * @throws ApiException
     */
    public function resolveId(
        string $name,
        float $price,
        array $taxes = [],
        string $exemptionReason = '',
        ?string $reference = null,
        ?string $createName = null
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

        // A product cannot be renamed once created, so it is created under a
        // generic, action-describing name ($createName) rather than the
        // order-specific line name; the line name still shows on the document.
        $productName = ($createName !== null && $createName !== '') ? $createName : $name;
        $insert = $this->buildInsert($productName, $reference, $price, $taxes, $exemptionReason);
        $created = $this->client->createProduct($insert);
        $productId = (int) ($created['productId'] ?? 0);

        if ($productId <= 0) {
            throw new ApiException('Moloni ON did not return a product id.', ['reference' => $reference]);
        }

        return $this->cache[$reference] = $productId;
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
            // Every WHMCS line is billed as a non-stock service; a service needs
            // no productAT (SAF-T goods classification).
            'type' => ProductType::SERVICE,
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
