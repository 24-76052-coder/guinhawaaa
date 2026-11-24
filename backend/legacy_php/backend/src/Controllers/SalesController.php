<?php

declare(strict_types=1);

namespace Guinhawa\Controllers;

use Guinhawa\Auth;
use Guinhawa\Database;
use Guinhawa\Http;
use Guinhawa\HttpException;
use Guinhawa\Inventory;
use Guinhawa\StateRepository;
use Guinhawa\Validator;

final class SalesController
{
    public static function checkout(): never
    {
        // Only cashiers can create sales. Admins retain read-only transaction
        // access (receipts, status updates and CSV export).
        $actor = Auth::requireRole(['counter-staff']);
        $body = Http::body();

        $transactionId = trim((string) ($body['transaction_id'] ?? ''));
        if ($transactionId !== '') {
            $prior = Database::fetchOne('SELECT id FROM sales WHERE transaction_id = ? LIMIT 1', [$transactionId]);
            if ($prior) {
                $state = StateRepository::snapshot($actor);
                Http::json(['state' => $state, 'sale' => self::findSale($state, (int) $prior['id'])]);
            }
        }

        $lines = self::normaliseLines($body['items'] ?? []);
        if (!$lines) {
            throw new HttpException('Your cart is empty. Add at least one product.', 422, 'VALIDATION');
        }

        $paymentMethod = (string) ($body['payment_method'] ?? '');
        if (!in_array($paymentMethod, Validator::PAYMENT_METHODS, true)) {
            throw new HttpException('Please choose a valid payment method.', 422, 'VALIDATION');
        }

        $customer = Validator::optionalText($body['customer'] ?? '', 'Customer', 160) ?: 'Walk-in Customer';
        $customerId = Validator::optionalText($body['customer_id'] ?? '', 'Customer ID', 20);
        $orderType = (string) ($body['order_type'] ?? 'Walk-in');
        if (!in_array($orderType, Validator::ORDER_TYPES, true)) {
            $orderType = 'Walk-in';
        }
        $discount = Validator::number($body['discount'] ?? 0, 'Discount', 0);
        $amountTendered = $body['amount_tendered'] ?? null;
        $today = date('Y-m-d');

        $saleId = Database::transaction(function () use ($lines, $paymentMethod, $orderType, $customer, $customerId, $discount, $transactionId, $actor, $today, $amountTendered): int {
            $priced = [];
            $subtotal = 0.0;
            // Kg and sack lines of the same product share one stock pool. Track
            // the running balance in PHP because rows are deducted only after
            // the whole cart has been priced.
            $stockPools = [];

            foreach ($lines as $line) {
                $variant = Inventory::variant($line['product_id'], $line['variant'], true);
                if (!$variant) {
                    throw new HttpException('One of the products in the cart no longer exists.', 404, 'NOT_FOUND');
                }
                if ((int) $variant['archived'] === 1) {
                    throw new HttpException("{$variant['product_name']} is archived and cannot be sold.", 422, 'VALIDATION');
                }
                if (!empty($variant['expiration_date']) && $variant['expiration_date'] < $today) {
                    throw new HttpException("{$variant['product_name']} has expired and cannot be sold.", 422, 'VALIDATION');
                }

                $soldQuantity = (float) $line['quantity'];
                $soldUnit = (string) $variant['unit'];
                $sackWeight = (float) ($variant['sack_weight_kg'] ?? 0);
                if (self::isSackUnit($soldUnit)) {
                    if (round($soldQuantity) !== $soldQuantity) {
                        throw new HttpException('Sacks must be sold in whole numbers.', 422, 'VALIDATION');
                    }
                }

                // Feed stock is kept in kilograms. A sack sale is converted to
                // this product's configured kg-per-sack before deduction.
                $deductVariant = (string) $line['variant'];
                $deductQuantity = $soldQuantity;
                $deductUnit = $soldUnit;
                $available = (float) $variant['stock'];
                if (self::isSackUnit($soldUnit) && $sackWeight > 0) {
                    $kgVariantKey = Inventory::kgVariantKey($line['product_id']);
                    if ($kgVariantKey !== null) {
                        $kgVariant = Inventory::variant($line['product_id'], $kgVariantKey, true);
                        if (!$kgVariant) {
                            throw new HttpException("{$variant['product_name']} has no kilogram stock to draw from.", 422, 'VALIDATION');
                        }
                        $deductVariant = $kgVariantKey;
                        $deductQuantity = round($soldQuantity * $sackWeight, 3);
                        $deductUnit = (string) $kgVariant['unit'];
                        $available = (float) $kgVariant['stock'];
                    }
                }

                $poolKey = $line['product_id'] . ':' . $deductVariant;
                if (array_key_exists($poolKey, $stockPools)) {
                    $available = $stockPools[$poolKey];
                }

                if ($deductQuantity > $available + 0.000001) {
                    $message = $deductUnit !== $soldUnit && self::isSackUnit($soldUnit)
                        ? 'Only ' . self::trimNumber($available) . " kg of {$variant['product_name']} are available"
                            . ' (1 sack = ' . self::trimNumber($sackWeight) . ' kg).'
                        : 'Only ' . self::trimNumber($available) . " {$soldUnit} of {$variant['product_name']} are available.";
                    throw new HttpException($message, 422, 'INSUFFICIENT_STOCK');
                }

                $next = round($available - $deductQuantity, 3);
                $stockPools[$poolKey] = $next;

                $total = round($variant['price'] * $soldQuantity, 2);
                $subtotal += $total;
                $priced[] = [
                    'product_id' => $line['product_id'],
                    'variant' => $line['variant'],
                    'product' => $variant['product_name'],
                    'category' => $variant['category'],
                    'unit' => $soldUnit,
                    'quantity' => $soldQuantity,
                    'price' => (float) $variant['price'],
                    'total' => $total,
                    'stock' => $available,
                    'next' => $next,
                    'deduct_variant' => $deductVariant,
                    'deduct_quantity' => $deductQuantity,
                    'deduct_unit' => $deductUnit,
                ];
            }

            $subtotal = round($subtotal, 2);
            if ($discount > $subtotal) {
                throw new HttpException('Discount cannot be higher than the subtotal.', 422, 'VALIDATION');
            }
            $total = round($subtotal - $discount, 2);

            $tendered = $total;
            if ($paymentMethod === 'Cash') {
                $tendered = Validator::number($amountTendered, 'Cash amount received', 0);
                if ($tendered < $total) {
                    throw new HttpException('The amount received is lower than the amount due.', 422, 'VALIDATION');
                }
            }

            $receiptNo = Inventory::nextSaleNumber();
            $transaction = $transactionId !== '' ? $transactionId : 'TXN-' . bin2hex(random_bytes(8));

            Database::execute(
                'INSERT INTO sales
                    (receipt_no, customer, customer_id, order_type, subtotal, discount, total, payment_method,
                     amount_tendered, change_due, status, cashier, staff_id, transaction_id, sale_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())',
                [
                    $receiptNo,
                    $customer,
                    $customerId !== '' ? $customerId : null,
                    $orderType,
                    $subtotal,
                    $discount,
                    $total,
                    $paymentMethod,
                    $tendered,
                    round($tendered - $total, 2),
                    'Paid',
                    $actor['name'],
                    $actor['code'],
                    $transaction,
                ],
            );
            $saleId = Database::lastId();

            foreach ($priced as $line) {
                Database::execute(
                    'INSERT INTO sale_items
                        (sale_id, product_id, variant_key, product_name, category, unit, quantity, price, total)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $saleId,
                        $line['product_id'],
                        $line['variant'],
                        $line['product'],
                        $line['category'],
                        $line['unit'],
                        $line['quantity'],
                        $line['price'],
                        $line['total'],
                    ],
                );

                $next = $line['next'];
                Inventory::setStock($line['product_id'], $line['deduct_variant'], $next);
                Database::execute('UPDATE products SET awaiting_stock = 0 WHERE id = ?', [$line['product_id']]);

                $remarks = "{$customer} · {$orderType} · {$paymentMethod} sale";
                if ($line['deduct_variant'] !== $line['variant']) {
                    $remarks .= " ({$line['quantity']} {$line['unit']} deducted as "
                        . self::trimNumber($line['deduct_quantity']) . " {$line['deduct_unit']})";
                }

                Inventory::recordMovement(
                    $receiptNo,
                    $line['product_id'],
                    $line['deduct_variant'],
                    $line['product'],
                    $line['deduct_unit'],
                    'Stock Out',
                    $line['deduct_quantity'],
                    $line['stock'],
                    $next,
                    $remarks,
                    $actor,
                );
            }

            Auth::businessAudit(
                'Sale Completed',
                "{$receiptNo}: ₱" . number_format($total, 2) . " via {$paymentMethod}",
                $actor,
            );

            return $saleId;
        });

        $state = StateRepository::snapshot($actor);
        Http::json(['state' => $state, 'sale' => self::findSale($state, $saleId)], 201);
    }

    public static function hold(): never
    {
        // Held orders are a POS feature and stay cashier-only.
        $actor = Auth::requireRole(['counter-staff']);
        $body = Http::body();
        $items = $body['items'] ?? [];
        if (!is_array($items) || !$items) {
            throw new HttpException('Add at least one product before suspending the order.', 422, 'VALIDATION');
        }
        if (count($items) > 200) {
            throw new HttpException('That cart is too large to suspend.', 422, 'VALIDATION');
        }

        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $clean[] = [
                'id' => Validator::integer($item['id'] ?? 0, 'Cart product'),
                'variant' => in_array(strtoupper((string) ($item['variant'] ?? 'A')), ['A', 'B'], true)
                    ? strtoupper((string) $item['variant'])
                    : 'A',
                'name' => Validator::text($item['name'] ?? '', 'Cart product name', 1, 190),
                'category' => Validator::optionalText($item['category'] ?? '', 'Category', 60),
                'image' => Validator::imageReference($item['image'] ?? '', 'Product image'),
                'price' => Validator::number($item['price'] ?? 0, 'Price', 0),
                'stock' => Validator::number($item['stock'] ?? 0, 'Stock', 0),
                'unit' => Validator::text($item['unit'] ?? 'Units', 'Unit', 1, 40),
                'sack_weight_kg' => Validator::number($item['sack_weight_kg'] ?? 0, 'Sack weight', 0),
                'qty' => Validator::quantity($item['qty'] ?? 0, 'Quantity', 0.01),
                'key' => Validator::optionalText($item['key'] ?? '', 'Cart key', 60),
            ];
        }

        if (!$clean) {
            throw new HttpException('Add at least one valid product before suspending the order.', 422, 'VALIDATION');
        }

        $paymentMethod = (string) ($body['payment_method'] ?? 'Cash');
        if (!in_array($paymentMethod, Validator::PAYMENT_METHODS, true)) {
            $paymentMethod = 'Cash';
        }

        $orderType = (string) ($body['order_type'] ?? 'Walk-in');
        if (!in_array($orderType, Validator::ORDER_TYPES, true)) {
            $orderType = 'Walk-in';
        }

        $discount = Validator::number($body['discount'] ?? 0, 'Discount', 0);
        $tendered = Validator::optionalText($body['amount_tendered'] ?? '', 'Amount tendered', 40);
        $holdNo = Inventory::nextHoldNumber();

        Database::execute(
            'INSERT INTO held_orders (hold_no, items_json, discount, payment_method, order_type, amount_tendered, cashier, hold_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())',
            [$holdNo, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $discount, $paymentMethod, $orderType, $tendered, $actor['code']],
        );

        $id = Database::lastId();
        $state = StateRepository::snapshot($actor);
        $held = null;
        foreach ($state['heldOrders'] as $order) {
            if ((int) $order['id'] === $id) {
                $held = $order;
                break;
            }
        }

        Http::json(['state' => $state, 'held' => $held], 201);
    }

    public static function setStatus(int $id): never
    {
        $actor = Auth::requireRole(['admin', 'counter-staff']);
        $body = Http::body();
        $status = trim((string) ($body['status'] ?? ''));

        $stored = [
            'Completed' => 'Paid',
            'Paid' => 'Paid',
            'Pending' => 'Pending',
            'Refunded' => 'Refunded',
            'Void' => 'Void',
        ];
        if (!isset($stored[$status])) {
            throw new HttpException('Choose a valid sale status.', 422, 'VALIDATION');
        }

        $sale = Database::fetchOne('SELECT * FROM sales WHERE id = ? LIMIT 1', [$id]);
        if (!$sale) {
            throw new HttpException('That sale no longer exists.', 404, 'NOT_FOUND');
        }
        if ($actor['role'] !== 'admin' && (string) $sale['staff_id'] !== (string) $actor['code']) {
            throw new HttpException('You can only update your own sales.', 403, 'FORBIDDEN');
        }

        Database::execute('UPDATE sales SET status = ? WHERE id = ?', [$stored[$status], $id]);
        Auth::businessAudit('Sale Status Updated', "{$sale['receipt_no']} marked {$status}", $actor);

        Http::json(['state' => StateRepository::snapshot($actor)]);
    }

    public static function removeHeld(int $id): never
    {
        $actor = Auth::requireRole(['admin', 'counter-staff']);
        Database::execute('DELETE FROM held_orders WHERE id = ?', [$id]);
        Http::json(['state' => StateRepository::snapshot($actor)]);
    }

    /** @return array<int, array{product_id:int,variant:string,quantity:float}> */
    private static function normaliseLines(mixed $items): array
    {
        if (!is_array($items) || !$items) {
            return [];
        }
        if (count($items) > 200) {
            throw new HttpException('That cart is too large to process.', 422, 'VALIDATION');
        }

        $grouped = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = Validator::integer($item['product_id'] ?? 0, 'Product');
            $variant = strtoupper(trim((string) ($item['variant'] ?? 'A')));
            if (!in_array($variant, ['A', 'B'], true)) {
                throw new HttpException('Choose a valid selling unit.', 422, 'VALIDATION');
            }
            $quantity = Validator::quantity($item['quantity'] ?? 0, 'Quantity', 0.01, 100000);
            $key = $productId . ':' . $variant;
            if (isset($grouped[$key])) {
                $grouped[$key]['quantity'] += $quantity;
            } else {
                $grouped[$key] = [
                    'product_id' => $productId,
                    'variant' => $variant,
                    'quantity' => $quantity,
                ];
            }
        }

        return array_values(array_map(static function (array $line): array {
            $line['quantity'] = round($line['quantity'], 3);
            return $line;
        }, $grouped));
    }

    private static function findSale(array $state, int $saleId): ?array
    {
        foreach ($state['sales'] as $sale) {
            if ((int) $sale['id'] === $saleId) {
                return $sale;
            }
        }
        return null;
    }

    private static function isSackUnit(string $unit): bool
    {
        return (bool) preg_match('/^(sack|sacks|bag|bags)$/i', trim($unit));
    }

    private static function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
