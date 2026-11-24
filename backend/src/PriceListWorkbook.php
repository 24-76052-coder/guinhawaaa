<?php

declare(strict_types=1);

namespace Guinhawa;

use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/** Reads catalogue rows without evaluating workbook formulas or changing stock. */
final class PriceListWorkbook
{
    public static function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('The price-list workbook is not readable.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The price-list workbook is not a valid XLSX archive.');
        }

        try {
            $workbook = self::xml($zip, 'xl/workbook.xml');
            $relationshipId = '';
            foreach ($workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [] as $sheet) {
                if ((string) $sheet['name'] === 'Product List') {
                    $ids = $sheet->xpath('./@*[local-name()="id"]');
                    $relationshipId = (string) ($ids[0] ?? '');
                    break;
                }
            }
            if ($relationshipId === '') {
                throw new RuntimeException('The workbook must contain a Product List sheet.');
            }

            $sheetPath = '';
            $relationships = self::xml($zip, 'xl/_rels/workbook.xml.rels');
            foreach ($relationships->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
                if ((string) $relationship['Id'] === $relationshipId) {
                    if (strcasecmp((string) $relationship['TargetMode'], 'External') === 0) {
                        throw new RuntimeException('The Product List sheet must be inside the workbook.');
                    }
                    $target = (string) $relationship['Target'];
                    $parts = [];
                    foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target) as $part) {
                        if ($part === '..') {
                            if (!$parts) {
                                throw new RuntimeException('The workbook contains an invalid sheet path.');
                            }
                            array_pop($parts);
                        } elseif ($part !== '' && $part !== '.') {
                            $parts[] = $part;
                        }
                    }
                    $sheetPath = implode('/', $parts);
                    break;
                }
            }
            if ($sheetPath === '') {
                throw new RuntimeException('The Product List sheet relationship is missing.');
            }

            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach (self::xml($zip, 'xl/sharedStrings.xml')->xpath('/*[local-name()="sst"]/*[local-name()="si"]') ?: [] as $item) {
                    $strings[] = self::richText($item);
                }
            }

            $expected = ['Category', 'Subcategory', 'Product name', 'Price A (₱)', 'Unit A', 'Price B (₱)', 'Unit B', 'Review', 'Notes', 'Source'];
            $headerFound = false;
            $products = [];
            $source = self::text(basename(str_replace('\\', '/', $path)), 'Source filename', 1, 160);
            $sheet = self::xml($zip, $sheetPath);
            foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
                $rowNumber = (int) $row['r'];
                $values = array_fill_keys(range('A', 'J'), '');
                $formulas = [];
                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    if (!preg_match('/^([A-Z]+)[1-9][0-9]*$/', (string) $cell['r'], $match) || !array_key_exists($match[1], $values)) {
                        continue;
                    }
                    $column = $match[1];
                    $formulas[$column] = (bool) $cell->xpath('./*[local-name()="f"]');
                    $valueNodes = $cell->xpath('./*[local-name()="v"]');
                    $value = (string) ($valueNodes[0] ?? '');
                    if ((string) $cell['t'] === 's') {
                        if (!ctype_digit($value) || !array_key_exists((int) $value, $strings)) {
                            throw new RuntimeException("Invalid shared text at {$column}{$rowNumber}.");
                        }
                        $value = $strings[(int) $value];
                    } elseif ((string) $cell['t'] === 'inlineStr') {
                        $inline = $cell->xpath('./*[local-name()="is"]');
                        $value = isset($inline[0]) ? self::richText($inline[0]) : '';
                    }
                    $values[$column] = trim($value);
                }

                if (!$headerFound) {
                    if ($values['A'] !== 'Category') {
                        continue;
                    }
                    if (array_values($values) !== $expected || in_array(true, $formulas, true)) {
                        throw new RuntimeException('The Product List columns must be Category, Subcategory, Product name, Price A (₱), Unit A, Price B (₱), Unit B, Review, Notes, Source.');
                    }
                    $headerFound = true;
                    continue;
                }

                // Summary/formatting rows without product identity are not item data.
                if ($values['A'] === '' && $values['B'] === '' && $values['C'] === '') {
                    continue;
                }
                if ($values['C'] === '' && $values['B'] === '') {
                    continue;
                }
                if ($rowNumber < 1) {
                    throw new RuntimeException('An item row has no valid Excel row number.');
                }
                if (in_array(true, $formulas, true)) {
                    throw new InvalidArgumentException("Row {$rowNumber}: formulas are not allowed in product data.");
                }

                $name = self::text($values['C'], "Row {$rowNumber} product name", 1, 190);
                $category = self::text($values['A'], "Row {$rowNumber} category", 1, 60);
                if (strcasecmp($category, 'Accessory') === 0) {
                    $category = 'Accessories';
                }
                $subcategory = self::text($values['B'], "Row {$rowNumber} subcategory", 0, 120);
                $originalReview = self::text($values['H'], "Row {$rowNumber} review", 1, 40);
                if (!in_array($originalReview, ['Listed', 'Verify'], true)) {
                    throw new InvalidArgumentException("Row {$rowNumber}: review must be Listed or Verify.");
                }
                $notes = [];
                if ($values['I'] !== '') {
                    $notes[] = self::text($values['I'], "Row {$rowNumber} notes", 0, 5000);
                }
                if ($values['J'] !== '') {
                    $notes[] = 'Source photo: ' . self::text($values['J'], "Row {$rowNumber} source photo", 0, 500) . '.';
                }
                $notes[] = 'Original review: ' . $originalReview . '.';
                $variants = [];
                foreach (['A' => ['D', 'E'], 'B' => ['F', 'G']] as $key => [$priceColumn, $unitColumn]) {
                    if ($key === 'B' && $values[$priceColumn] === '') {
                        continue;
                    }
                    $price = $values[$priceColumn];
                    if ($price === '' || !is_numeric($price) || !is_finite((float) $price) || (float) $price < 0 || (float) $price > 9999999999.99) {
                        throw new InvalidArgumentException("Row {$rowNumber}: Price {$key} must be a nonnegative number with a valid database range.");
                    }
                    $rawUnit = self::text($values[$unitColumn], "Row {$rowNumber} Unit {$key}", 0, 80);
                    [$unit, $assumed] = self::unit($rawUnit);
                    if ($assumed) {
                        $notes[] = "Unit {$key} requires verification; workbook label: " . ($rawUnit === '' ? '(blank)' : $rawUnit) . '.';
                    }
                    $variants[] = [
                        'key' => $key,
                        'label' => 'Price ' . $key,
                        'unit' => $unit,
                        'unit_raw' => $rawUnit,
                        'price' => round((float) $price, 2),
                        'stock' => 0,
                        'minimum' => 0,
                        'assumed' => $assumed,
                        'unit_verified' => !$assumed,
                    ];
                }
                if (count($variants) === 2 && $variants[0]['unit'] === $variants[1]['unit']) {
                    throw new InvalidArgumentException("Row {$rowNumber}: Price A and Price B must have distinct selling units.");
                }
                $uncertain = in_array(true, array_column($variants, 'assumed'), true);
                $products[] = [
                    'name' => $name,
                    'category' => Validator::category($category),
                    'subcategory' => $subcategory,
                    'description' => $subcategory,
                    'notes' => self::text(implode("\n", $notes), "Row {$rowNumber} combined notes", 0, 5000),
                    'source' => $source,
                    'sheet_row' => $rowNumber,
                    'review' => $originalReview === 'Verify' || $uncertain ? 'Verify' : 'Listed',
                    'variants' => $variants,
                ];
            }
            if (!$headerFound || !$products) {
                throw new RuntimeException('The Product List sheet has no valid header and product rows.');
            }
            return $products;
        } finally {
            $zip->close();
        }
    }

    private static function xml(ZipArchive $zip, string $entry): SimpleXMLElement
    {
        $data = $zip->getFromName($entry);
        if ($data === false || stripos($data, '<!DOCTYPE') !== false) {
            throw new RuntimeException("Missing or unsupported workbook XML: {$entry}.");
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($data, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw new RuntimeException("Invalid workbook XML: {$entry}.");
            }
            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function richText(SimpleXMLElement $item): string
    {
        $result = '';
        foreach ($item->xpath('./*[local-name()="t"] | ./*[local-name()="r"]/*[local-name()="t"]') ?: [] as $text) {
            $result .= (string) $text;
        }
        return $result;
    }

    private static function text(string $value, string $label, int $min, int $max): string
    {
        $value = trim($value);
        // Report parser errors independently of the API's HttpException loader.
        if (str_contains($value, "\0") || mb_strlen($value) < $min || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} must contain {$min}–{$max} valid characters.");
        }
        return $min === 0 ? Validator::optionalText($value, $label, $max) : Validator::text($value, $label, $min, $max);
    }

    private static function unit(string $raw): array
    {
        $assumed = (bool) preg_match('/\(assumed\)/iu', $raw);
        $key = mb_strtolower(trim((string) preg_replace('/\s*\(assumed\)\s*/iu', '', $raw)));
        $units = [
            'kg' => 'Kilograms', 'kilo' => 'Kilograms', 'kilos' => 'Kilograms',
            'kilogram' => 'Kilograms', 'kilograms' => 'Kilograms',
            'sack' => 'Sacks', 'sacks' => 'Sacks', 'bag' => 'Bags', 'bags' => 'Bags',
            'piece' => 'Pieces', 'pieces' => 'Pieces', 'tablet' => 'Tablets', 'tablets' => 'Tablets',
            'bottle' => 'Bottles', 'bottles' => 'Bottles', 'box' => 'Boxes', 'boxes' => 'Boxes',
            'can' => 'Cans', 'cans' => 'Cans', 'pouch' => 'Pouches', 'pouches' => 'Pouches',
            'pack' => 'Packs', 'packs' => 'Packs', 'unit' => 'Units', 'units' => 'Units',
            'tray' => 'Trays', 'trays' => 'Trays', 'head' => 'Heads', 'heads' => 'Heads',
            'liter' => 'Liters', 'liters' => 'Liters',
        ];
        return [Validator::unit($units[$key] ?? 'Units'), $assumed || !isset($units[$key])];
    }
}
