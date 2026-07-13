<?php

/**
 * WHMCS hooks for the Moloni ON addon.
 *
 * When "auto create" is enabled in settings, a Moloni ON document is created
 * automatically once a WHMCS invoice is paid. Failures are logged and never
 * interrupt WHMCS processing.
 */

declare(strict_types=1);

use MoloniOn\Api\ApiClient;
use MoloniOn\Api\MoloniClient;
use MoloniOn\Exceptions\SkippedException;
use MoloniOn\Facades\LoggerFacade;
use MoloniOn\Models\Order;
use MoloniOn\Models\Whmcs;
use MoloniOn\Services\AuthService;
use MoloniOn\Services\DocumentService;
use MoloniOn\Services\SettingsService;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/vendor/autoload.php';

add_hook('InvoicePaid', 1, static function (array $vars): void {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);

    try {
        $settings = new SettingsService();

        // Auto-create disabled: stay silent — this hook fires on every paid
        // invoice, so logging here would be pure noise for installs that don't
        // want automatic documents.
        if (!$settings->autoCreate()) {
            return;
        }

        // From here on, every exit path leaves a log line: the auto-create hook
        // must never be a silent black box (it is the only clue when a paid
        // order does not turn into a document). See journal 039.
        $order = $invoiceId > 0 ? Whmcs::getOrderByInvoice($invoiceId) : null;

        if ($order === null) {
            LoggerFacade::info(
                'Auto-create skipped: paid invoice is not linked to a WHMCS order.',
                ['invoice_id' => $invoiceId]
            );

            return;
        }

        $orderId = (int) $order->id;

        LoggerFacade::info(
            'Auto-create triggered by paid invoice.',
            ['invoice_id' => $invoiceId, 'order_id' => $orderId],
            $orderId
        );

        // Skip orders already handled (synced or explicitly discarded).
        $tracked = Order::findByOrderId($orderId);

        if ($tracked !== null && in_array($tracked->status, [Order::STATUS_SYNCED, Order::STATUS_DISCARDED], true)) {
            LoggerFacade::info(
                'Auto-create skipped: order already ' . $tracked->status . '.',
                ['invoice_id' => $invoiceId, 'order_id' => $orderId],
                $orderId
            );

            return;
        }

        $api = new ApiClient();
        $moloni = new MoloniClient($api);
        $auth = new AuthService($api, $moloni);

        if (!$auth->ensureAuthenticated() || !$auth->hasCompany()) {
            LoggerFacade::warning('Auto-create skipped: not authenticated.', ['order_id' => $orderId], $orderId);

            return;
        }

        // Populate Context::$company so the document uses the right fiscal zone
        // (the admin UI does this in the dispatcher; the hook must do it too).
        $auth->loadCompany();

        (new DocumentService($moloni, $settings))->createDocumentFromOrder($orderId);
    } catch (SkippedException $e) {
        // Not a failure: the order was intentionally not billed (e.g. no
        // invoice items / mass-payment invoice). DocumentService already marked
        // it discarded and logged the reason; record it here too, at info.
        LoggerFacade::info('Auto-create skipped: ' . $e->getMessage(), ['invoice_id' => $invoiceId]);
    } catch (Throwable $e) {
        LoggerFacade::error(
            'Automatic document creation failed.',
            ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]
        );
    }
});
