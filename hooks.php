<?php

/**
 * WHMCS hooks for the Moloni ON addon.
 *
 * When "auto create" is enabled in settings, a Moloni ON document is created
 * automatically once a WHMCS invoice is paid. Failures are logged and never
 * interrupt WHMCS processing.
 */

declare(strict_types=1);

use Moloni\Api\ApiClient;
use Moloni\Api\MoloniClient;
use Moloni\Facades\LoggerFacade;
use Moloni\Models\Order;
use Moloni\Models\Whmcs;
use Moloni\Services\AuthService;
use Moloni\Services\DocumentService;
use Moloni\Services\SettingsService;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/vendor/autoload.php';

add_hook('InvoicePaid', 1, static function (array $vars): void {
    try {
        $settings = new SettingsService();

        if (!$settings->autoCreate()) {
            return;
        }

        $invoiceId = (int) ($vars['invoiceid'] ?? 0);
        $order = $invoiceId > 0 ? Whmcs::getOrderByInvoice($invoiceId) : null;

        if ($order === null) {
            return;
        }

        $orderId = (int) $order->id;

        // Skip orders already handled (synced or explicitly discarded).
        $tracked = Order::findByOrderId($orderId);

        if ($tracked !== null && in_array($tracked->status, [Order::STATUS_SYNCED, Order::STATUS_DISCARDED], true)) {
            return;
        }

        $api = new ApiClient();
        $moloni = new MoloniClient($api);
        $auth = new AuthService($api, $moloni);

        if (!$auth->ensureAuthenticated() || !$auth->hasCompany()) {
            LoggerFacade::warning('Auto-create skipped: not authenticated.', ['order_id' => $orderId], $orderId);

            return;
        }

        (new DocumentService($moloni, $settings))->createDocumentFromOrder($orderId);
    } catch (Throwable $e) {
        LoggerFacade::error('Automatic document creation failed.', ['error' => $e->getMessage()]);
    }
});
