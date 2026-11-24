<?php
/**
 * Import the Pet Product Price List workbook without replacing existing data.
 *
 * Preview: php backend/scripts/import_price_list.php "C:/path/price-list.xlsx"
 * Import:  php backend/scripts/import_price_list.php "C:/path/price-list.xlsx" --apply
 *
 * Repeated imports skip matching source rows. Existing prices, inventory and
 * account records are never updated. All new products start with zero stock.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This import can only be run from the command line.\n");
}

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Http.php';
require __DIR__ . '/../src/Validator.php';
require __DIR__ . '/../src/PriceListWorkbook.php';

use Guinhawa\Database;
use Guinhawa\PriceListWorkbook;

$apply = false;
$path = null;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $apply = true;
    } elseif ($path === null && !str_starts_with($argument, '--')) {
        $path = $argument;
    } else {
        fwrite(STDERR, "Usage: php import_price_list.php workbook.xlsx [--apply]\n");
        exit(1);
    }
}
if ($path === null) {
    fwrite(STDERR, "Usage: php import_price_list.php workbook.xlsx [--apply]\n");
    exit(1);
}

$locked = false;
try {
    // Validate the complete workbook before making any database changes.
    $products = PriceListWorkbook::read($path);
    $config = require __DIR__ . '/../config/config.php';
    Database::configure($config['db']);

    $report = [
        'mode' => $apply ? 'applied' : 'preview',
        'source' => basename($path),
        'sha256' => hash_file('sha256', $path),
        'workbook_products' => count($products),
        'new_products' => 0,
        'existing_products_skipped' => 0,
        'new_selling_units' => 0,
        'products_needing_review' => 0,
        'categories' => [],
        'opening_stock_per_unit' => 0,
    ];
    foreach ($products as $product) {
        $report['categories'][$product['category']] = ($report['categories'][$product['category']] ?? 0) + 1;
        $report['products_needing_review'] += $product['review'] === 'Verify' ? 1 : 0;
    }

    if ($apply) {
        $lock = Database::fetchOne("SELECT GET_LOCK('guinhawa_catalog_import', 10) AS acquired");
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Another catalog import is in progress. Try again later.');
        }
        $locked = true;
    }

    $import = static function () use ($products, $apply, &$report): void {
        $existing = Database::fetchAll('SELECT id, sku, name, category, subcategory, source, sheet_row FROM products');
        $bySourceRow = [];
        $skus = [];
        $nextNumber = 2000;
        foreach ($existing as $product) {
            $key = $product['source'] . ':' . $product['sheet_row'];
            $bySourceRow[$key][] = $product;
            $skus[strtolower($product['sku'])] = true;
            $nextNumber = max($nextNumber, 2000 + (int) $product['id']);
        }

        foreach ($products as $product) {
            $key = $product['source'] . ':' . $product['sheet_row'];
            $prior = $bySourceRow[$key] ?? [];
            if (count($prior) > 1) {
                throw new RuntimeException("Multiple products already reference workbook row {$product['sheet_row']}; nothing was imported.");
            }
            if ($prior) {
                foreach (['name', 'category', 'subcategory'] as $field) {
                    if ($prior[0][$field] !== $product[$field]) {
                        throw new RuntimeException("Workbook row {$product['sheet_row']} differs from its previous import. Existing records were preserved.");
                    }
                }
                $report['existing_products_skipped']++;
                continue;
            }

            do {
                $sku = sprintf('GPS-%04d', ++$nextNumber);
            } while (isset($skus[strtolower($sku)]));
            $skus[strtolower($sku)] = true;
            $report['new_products']++;
            $report['new_selling_units'] += count($product['variants']);
            if (!$apply) {
                continue;
            }

            Database::execute(
                'INSERT INTO products
                    (sku, name, category, subcategory, description, review, notes, source, sheet_row, awaiting_stock)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [$sku, $product['name'], $product['category'], $product['subcategory'], $product['description'],
                    $product['review'], $product['notes'], $product['source'], $product['sheet_row']],
            );
            $productId = Database::lastId();
            foreach ($product['variants'] as $variant) {
                Database::execute(
                    'INSERT INTO product_variants
                        (product_id, variant_key, label, unit, unit_raw, price, assumed, unit_verified)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$productId, $variant['key'], 'Price ' . $variant['key'], $variant['unit'], $variant['unit_raw'],
                        $variant['price'], (int) $variant['assumed'], (int) $variant['unit_verified']],
                );
                Database::execute(
                    'INSERT INTO inventory (product_id, variant_key, quantity) VALUES (?, ?, 0)',
                    [$productId, $variant['key']],
                );
            }
        }

        if ($apply && $report['new_products'] > 0) {
            Database::execute(
                'INSERT INTO audit_logs (actor, action, details) VALUES (?, ?, ?)',
                ['System import', 'Product Catalog Imported', sprintf(
                    '%s: added %d products and %d selling units with zero opening stock. SHA-256: %s',
                    $report['source'], $report['new_products'], $report['new_selling_units'], $report['sha256'],
                )],
            );
        }
    };

    if ($apply) {
        Database::transaction($import);
    } else {
        $import();
    }

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Import failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($locked) {
        Database::fetchOne("SELECT RELEASE_LOCK('guinhawa_catalog_import') AS released");
    }
}
