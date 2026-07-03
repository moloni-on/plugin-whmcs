<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Api\MoloniClient;
use Moloni\Exceptions\ApiException;
use Moloni\Models\Whmcs;

/**
 * Builds a document's payment line from a WHMCS order's payment gateway.
 *
 * The Moloni ON payment method is matched by the gateway's display name; when no
 * method matches, the configured default (settings) is used, and only if there
 * is no default either is one created on the fly. The payment is labelled with
 * the gateway's display name and valued at the order total.
 */
class PaymentResolver
{
    private MoloniClient $client;

    private SettingsService $settings;

    public function __construct(MoloniClient $client, SettingsService $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
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
     * Resolve the Moloni payment method id for a gateway name:
     *   1. an existing method whose name matches the gateway,
     *   2. otherwise the configured default payment method,
     *   3. otherwise create one named after the gateway.
     *
     * @throws ApiException
     */
    private function resolvePaymentMethodId(string $name): int
    {
        $existing = $this->client->findPaymentMethodByName($name);

        if ($existing !== null && !empty($existing['paymentMethodId'])) {
            return (int) $existing['paymentMethodId'];
        }

        $default = $this->settings->paymentMethodId();

        if ($default > 0) {
            return $default;
        }

        $created = $this->client->createPaymentMethod(['name' => $name]);

        return (int) ($created['paymentMethodId'] ?? 0);
    }
}
