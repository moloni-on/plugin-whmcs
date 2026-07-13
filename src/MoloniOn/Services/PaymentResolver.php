<?php

declare(strict_types=1);

namespace MoloniOn\Services;

use MoloniOn\Api\MoloniClient;
use MoloniOn\Exceptions\ApiException;
use MoloniOn\Models\Whmcs;
use MoloniOn\Support\CurrencyExchange;

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
     * @param CurrencyExchange|null $exchange Applied to the payment value when the
     *        order currency differs from the company base currency.
     * @return array<int,array<string,mixed>> Zero or one payment entry.
     * @throws ApiException
     */
    public function resolve($order, ?CurrencyExchange $exchange = null): array
    {
        $name = Whmcs::getGatewayName((string) ($order->paymentmethod ?? ''));

        if ($name === null) {
            return [];
        }

        $paymentMethodId = $this->resolvePaymentMethodId($name);

        if ($paymentMethodId <= 0) {
            return [];
        }

        // The order total is in the client currency; convert to the company base
        // currency when an exchange applies.
        $value = (float) ($order->amount ?? 0);

        return [[
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodName' => $name,
            'date' => date('Y-m-d H:i:s'),
            'value' => $exchange !== null ? $exchange->toBase($value) : $value,
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
