<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Exceptions\ApiException;
use Moloni\Models\Whmcs;

/**
 * Builds a document's payment line from a WHMCS order's payment gateway,
 * resolving (or creating) the matching Moloni ON payment method by name.
 *
 * Mirrors the Moloni ON WooCommerce plugin: the payment is labelled with the
 * gateway's display name and valued at the order total.
 */
class PaymentResolver
{
    private MoloniClient $client;

    public function __construct(MoloniClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param object $order tblorders row
     * @return array<int,array<string,mixed>> Zero or one payment entry.
     * @throws ApiException
     */
    public function resolve($order): array
    {
        $name = Whmcs::getGatewayName((string) ($order->paymentmethod ?? ''));

        if ($name === null) {
            return [];
        }

        $paymentMethodId = $this->resolvePaymentMethodId($name);

        if ($paymentMethodId <= 0) {
            return [];
        }

        return [[
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodName' => $name,
            'date' => date('Y-m-d H:i:s'),
            'value' => (float) ($order->amount ?? 0),
        ]];
    }

    /**
     * @throws ApiException
     */
    private function resolvePaymentMethodId(string $name): int
    {
        $existing = $this->client->findPaymentMethodByName($name);

        if ($existing !== null && !empty($existing['paymentMethodId'])) {
            return (int) $existing['paymentMethodId'];
        }

        $created = $this->client->createPaymentMethod(['name' => $name]);

        return (int) ($created['paymentMethodId'] ?? 0);
    }
}
