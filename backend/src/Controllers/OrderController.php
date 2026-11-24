<?php

declare(strict_types=1);

namespace Guinhawa\Controllers;

use Guinhawa\Auth;
use Guinhawa\Database;
use Guinhawa\Http;
use Guinhawa\HttpException;
use Guinhawa\Schema;
use Guinhawa\StateRepository;
use Guinhawa\Validator;

final class OrderController
{
    public static function setStatus(int $id): never
    {
        $actor = Auth::requireAdmin();
        $status = (string) (Http::body()['status'] ?? '');
        if (!in_array($status, Validator::ORDER_STATUSES, true)) {
            throw new HttpException('Please choose a valid order status.', 422, 'VALIDATION');
        }

        $stored = strtolower($status);
        $order = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('orders') . ' WHERE source = "orders" AND id = ? LIMIT 1',
            [$id],
        );
        if (!$order) {
            throw new HttpException('Order not found.', 404, 'NOT_FOUND');
        }

        Database::execute(
            'UPDATE ' . Schema::t('orders') . ' SET status = ? WHERE id = ?',
            [$stored, $id],
        );
        Auth::businessAudit(
            $status === 'Cancelled' ? 'Order Cancelled' : 'Order Status Changed',
            "Order #{$id} changed from {$order['status']} to {$status}",
            $actor,
        );

        Http::json(['state' => StateRepository::snapshot($actor)]);
    }
}
