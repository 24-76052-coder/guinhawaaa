<?php

declare(strict_types=1);

namespace Guinhawa;

final class Inventory
{
    public static function nextReference(string $prefix): string
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(reference, '-', -1) AS UNSIGNED)), 0) + 1 AS next_number
             FROM " . Schema::t('inventory_transactions') . "
             WHERE reference LIKE ?",
            [$prefix . '-%'],
        );
        return sprintf('%s-%03d', $prefix, (int) $row['next_number']);
    }

    public static function nextSaleNumber(): string
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(order_no, '-', -1) AS UNSIGNED)), 0) + 1 AS next_number
             FROM " . Schema::t('orders') . "
             WHERE order_no LIKE 'RCP-%'",
        );
        return sprintf('RCP-%03d', (int) $row['next_number']);
    }

    public static function nextHoldNumber(): string
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(order_no, '-', -1) AS UNSIGNED)), 0) + 1 AS next_number
             FROM " . Schema::t('orders') . "
             WHERE order_no LIKE 'HLD-%'",
        );
        return sprintf('HLD-%03d', (int) $row['next_number']);
    }

    /** Next SUP-001 / CUS-001 style code for a partner table. */
    public static function nextCode(string $table, string $prefix): string
    {
        $allowed = ['suppliers', 'customers'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported code table.');
        }
        $row = Database::fetchOne('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM ' . Schema::t($table));
        return sprintf('%s-%03d', $prefix, (int) $row['next_id']);
    }

    public static function product(int $id, bool $lock = false): array
    {
        $sql = 'SELECT * FROM ' . Schema::t('products') . ' WHERE id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $row = Database::fetchOne($sql, [$id]);
        if (!$row) {
            throw new HttpException('Product not found.', 404, 'NOT_FOUND');
        }
        return $row;
    }

    /**
     * Product + variant + current stock, locked for the duration of a movement
     * transaction. Current stock now lives in `product_variants.quantity`.
     */
    public static function variant(int $productId, string $variantKey, bool $lock = false): ?array
    {
        $sql = 'SELECT p.id AS product_id, p.name AS product_name, p.category, p.archived,
                       p.expiration_date, p.sack_weight_kg,
                       v.variant_key, v.unit, v.price, v.unit_verified,
                       v.quantity AS stock
                FROM ' . Schema::t('products') . ' p
                JOIN ' . Schema::t('product_variants') . ' v ON v.product_id = p.id
                WHERE p.id = ? AND v.variant_key = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        return Database::fetchOne($sql, [$productId, $variantKey]);
    }

    public static function resolveVariantKey(int $productId, string $requested, string $movementUnit = ''): string
    {
        $requested = strtoupper(trim($requested));
        if ($requested === 'A' || $requested === 'B') {
            return $requested;
        }
        if ($movementUnit !== '') {
            $row = Database::fetchOne(
                'SELECT variant_key FROM ' . Schema::t('product_variants') . '
                 WHERE product_id = ? AND LOWER(unit) = LOWER(?) LIMIT 1',
                [$productId, $movementUnit],
            );
            if ($row) {
                return $row['variant_key'];
            }
        }
        return 'A';
    }

    /** The kilogram selling unit that backs sack stock, when one exists. */
    public static function kgVariantKey(int $productId): ?string
    {
        $row = Database::fetchOne(
            'SELECT variant_key FROM ' . Schema::t('product_variants') . '
             WHERE product_id = ?
               AND LOWER(TRIM(unit)) IN ("kg", "kilo", "kilos", "kilogram", "kilograms")
             ORDER BY variant_key ASC LIMIT 1',
            [$productId],
        );
        return $row ? (string) $row['variant_key'] : null;
    }

    public static function setStock(int $productId, string $variantKey, float $quantity): void
    {
        Database::execute(
            'INSERT INTO ' . Schema::t('product_variants') . '
                (product_id, variant_key, label, unit, quantity, stock_updated_at)
             VALUES (?, ?, "Price A", "Units", ?, NOW())
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), stock_updated_at = NOW()',
            [$productId, $variantKey, $quantity],
        );
    }

    /** Insert a movement row and return its id. */
    public static function recordMovement(
        string $reference,
        int $productId,
        string $variantKey,
        string $productName,
        string $unit,
        string $action,
        float $quantity,
        float $previous,
        float $next,
        string $remarks,
        array $actor,
        array $extra = [],
    ): int {
        [$type, $direction] = self::movementType($action, $next, $previous);

        Database::execute(
            'INSERT INTO ' . Schema::t('inventory_transactions') . '
                (legacy_source, transaction_type, direction, reference, product_id, variant_key,
                 product_name, unit, quantity, previous_stock, new_stock, supplier_id, supplier_name,
                 is_delivery, customer, reason, remarks, user_label, actor_id, actor_type, movement_date)
             VALUES ("application", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())',
            [
                $type,
                $direction,
                $reference,
                $productId,
                $variantKey,
                $productName,
                $unit,
                $quantity,
                $previous,
                $next,
                $extra['supplier_id'] ?? null,
                (string) ($extra['supplier_name'] ?? ''),
                (int) ($extra['is_delivery'] ?? 0),
                (string) ($extra['customer'] ?? ''),
                (string) ($extra['reason'] ?? ''),
                mb_substr($remarks, 0, 500),
                $actor['name'] ?? 'System',
                self::actorId($actor),
                $actor['type'] ?? null,
            ],
        );
        return Database::lastId();
    }

    /** @return array{0: string, 1: string} */
    private static function movementType(string $action, float $next, float $previous): array
    {
        return match ($action) {
            'Stock In' => ['stock_in', 'in'],
            'Damaged' => ['damaged', 'out'],
            'Expired' => ['expired', 'out'],
            'Adjustment' => ['adjustment', $next >= $previous ? 'in' : 'out'],
            'Sale' => ['sale', 'out'],
            default => ['stock_out', 'out'],
        };
    }

    private static function actorId(array $actor): ?int
    {
        if (empty($actor['id'])) {
            return null;
        }
        $id = (int) $actor['id'];
        $row = Database::fetchOne(
            'SELECT id FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [$id],
        );
        return $row ? $id : null;
    }
}
