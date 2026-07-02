<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Facades\LoggerFacade;
use Moloni\Models\Document;
use Moloni\Models\Order;
use Moloni\Models\Whmcs;

/**
 * Provides the order/document lists shown in the UI and the discard/revert
 * state transitions.
 */
class OrderService
{
    /**
     * WHMCS orders that still need action (not synced, not discarded).
     *
     * @return array<int,object>
     */
    public function getPendingOrders(): array
    {
        $tracking = $this->trackingByOrderId();
        $pending = [];

        foreach (Whmcs::ordersWithClients() as $order) {
            $status = $tracking[(int) $order->id]->status ?? Order::STATUS_PENDING;

            if (in_array($status, [Order::STATUS_SYNCED, Order::STATUS_DISCARDED], true)) {
                continue;
            }

            $order->sync_status = $status;
            $order->error_message = $tracking[(int) $order->id]->error_message ?? null;
            $pending[] = $order;
        }

        return $pending;
    }

    /**
     * Documents already created in Moloni ON.
     *
     * @return array<int,object>
     */
    public function getCreatedDocuments(): array
    {
        return Document::query()
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /**
     * Orders explicitly marked "do not sync".
     *
     * @return array<int,object>
     */
    public function getDiscardedOrders(): array
    {
        $discarded = Order::query()
            ->where('status', Order::STATUS_DISCARDED)
            ->pluck('order_id')
            ->map('intval')
            ->all();

        if ($discarded === []) {
            return [];
        }

        $lookup = array_flip($discarded);

        return array_values(array_filter(
            Whmcs::ordersWithClients(),
            static fn ($order): bool => isset($lookup[(int) $order->id])
        ));
    }

    public function discardOrder(int $orderId): void
    {
        Order::setStatus($orderId, Order::STATUS_DISCARDED);
        LoggerFacade::info('Order discarded.', ['order_id' => $orderId], $orderId);
    }

    public function revertDiscard(int $orderId): void
    {
        Order::setStatus($orderId, Order::STATUS_PENDING);
        LoggerFacade::info('Order moved back to pending.', ['order_id' => $orderId], $orderId);
    }

    /**
     * @return array<int,object> keyed by order_id
     */
    private function trackingByOrderId(): array
    {
        $out = [];

        foreach (Order::query()->get() as $row) {
            $out[(int) $row->order_id] = $row;
        }

        return $out;
    }
}
