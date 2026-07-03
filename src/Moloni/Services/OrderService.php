<?php

declare(strict_types=1);

namespace Moloni\Services;

use Moloni\Facades\LoggerFacade;
use Moloni\Models\Document;
use Moloni\Models\Order;
use Moloni\Models\Whmcs;
use Moloni\Support\Paginator;

/**
 * Provides the order/document lists shown in the UI and the discard/revert
 * state transitions.
 */
class OrderService
{
    /**
     * WHMCS orders that still need action (not synced, not discarded), one page
     * at a time. Filtering by tracking status happens in PHP, so the page is
     * sliced from the assembled list rather than in SQL.
     */
    public function getPendingOrders(int $page = 1, int $perPage = Paginator::PER_PAGE): Paginator
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

        return Paginator::fromSlice($pending, $page, $perPage);
    }

    /**
     * Documents already created in Moloni ON, one page at a time.
     */
    public function getCreatedDocuments(int $page = 1, int $perPage = Paginator::PER_PAGE): Paginator
    {
        return Paginator::paginate(
            $page,
            (int) Document::query()->count(),
            $perPage,
            static fn (int $offset, int $limit): array => Document::query()
                ->orderByDesc('id')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->all()
        );
    }

    /**
     * Orders explicitly marked "do not sync", one page at a time. The discard
     * set is filtered against the recent-orders window in PHP, so the page is
     * sliced from the assembled list.
     */
    public function getDiscardedOrders(int $page = 1, int $perPage = Paginator::PER_PAGE): Paginator
    {
        $discarded = Order::query()
            ->where('status', Order::STATUS_DISCARDED)
            ->pluck('order_id')
            ->map('intval')
            ->all();

        if ($discarded === []) {
            return Paginator::fromSlice([], $page, $perPage);
        }

        $lookup = array_flip($discarded);

        $orders = array_values(array_filter(
            Whmcs::ordersWithClients(),
            static fn ($order): bool => isset($lookup[(int) $order->id])
        ));

        return Paginator::fromSlice($orders, $page, $perPage);
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
