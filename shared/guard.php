<?php
/**
 * Session and role guard for the organized dashboard folders.
 *
 * Every page in admin/, inventory/ and cashier/ starts by including this file.
 * It checks the shared PHP session, forces a temporary password to be replaced,
 * and verifies that the signed-in role may open the requested folder and page.
 */

declare(strict_types=1);

require __DIR__ . '/../backend/bootstrap.php';

use Guinhawa\AccountService;
use Guinhawa\Auth;
use Guinhawa\Web;

Web::boot($config);

$script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
$area = basename(dirname($script));
$page = basename($script, '.php');

$areaRoles = [
    'admin' => ['admin'],
    'inventory' => ['admin', 'inventory-staff'],
    'cashier' => ['cashier', 'counter-staff'],
];

$areaPages = [
    'admin' => [
        'index', 'products', 'inventory', 'stock-in', 'stock-out', 'suppliers',
        'transactions', 'attendance', 'history', 'accounts', 'reports', 'settings',
    ],
    'inventory' => [
        'index', 'products', 'inventory', 'stock-in', 'stock-out', 'history',
        'attendance', 'settings',
    ],
    'cashier' => [
        'index', 'products', 'transactions', 'attendance', 'settings',
    ],
];

if (!isset($areaPages[$area]) || !in_array($page, $areaPages[$area], true)) {
    Web::abort(404, 'Page not found', 'That page does not exist.');
}

if (!AccountService::adminExists()) {
    Web::setupRequired();
}

$user = Auth::user();
if (!$user) {
    $target = $area . '/' . $page . '.php';
    Web::go(Web::url('login/index.php') . '?expired=1&redirect=' . rawurlencode($target));
}

if (!empty($user['mustChangePassword'])) {
    Web::go(Web::url('login/change-password.php?required=1'));
}

$role = (string) ($user['role'] ?? '');
if (!in_array($role, $areaRoles[$area], true)) {
    Web::abort(403, 'Access denied', 'Your account cannot open that page.');
}
