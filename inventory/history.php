<?php
/**
 * Legacy Inventory History URL.
 * The ledger now lives inside the Inventory page's "Movement history" tab.
 */
require __DIR__ . '/../shared/guard.php';

use Guinhawa\Web;

$area = basename(__DIR__);
Web::go(Web::url($area . '/inventory.php?tab=history'));
