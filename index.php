<?php
/**
 * Entry point for /guinhawa_system/.
 *
 * Signed-in users are sent to the dashboard page for their role; everyone else
 * goes to the sign-in screen. Every area lives in its own folder:
 * admin/, inventory/ and cashier/.
 */

declare(strict_types=1);

require __DIR__ . '/backend/bootstrap.php';

use Guinhawa\AccountService;
use Guinhawa\Auth;
use Guinhawa\Web;

Web::boot($config);

if (!AccountService::adminExists()) {
    Web::setupRequired();
}

$user = Auth::user();
if (!$user) {
    Web::go(Web::url('login/index.php'));
}

if (!empty($user['mustChangePassword'])) {
    Web::go(Web::url('login/change-password.php?required=1'));
}

$role = (string) ($user['role'] ?? '');
$home = match ($role) {
    'admin' => 'admin/index.php',
    'inventory-staff' => 'inventory/index.php',
    'cashier', 'counter-staff' => 'cashier/index.php',
    default => null,
};

if ($home === null) {
    // An account without a dashboard area would loop between here and the
    // sign-in page. End the session and let the administrator reassign a role.
    Auth::logout();
    Web::go(Web::url('login/index.php?denied=1'));
}

Web::go(Web::url($home));
