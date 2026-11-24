<?php
/**
 * Legacy Stock Out URL.
 * Stock releases are now recorded in the Inventory page's side panel.
 */
require __DIR__ . '/../shared/guard.php';

use Guinhawa\Web;

$area = basename(__DIR__);
$query = ['panel' => 'out'];
if (!empty($_GET['product'])) {
    $query['product'] = (string) $_GET['product'];
}
Web::go(Web::url($area . '/inventory.php?' . http_build_query($query)));
