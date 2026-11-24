<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Guinhawa\Auth;
use Guinhawa\Controllers\AppearanceController;
use Guinhawa\Controllers\AttendanceController;
use Guinhawa\Controllers\AuthController;
use Guinhawa\Controllers\EmployeeController;
use Guinhawa\Controllers\MailSettingsController;
use Guinhawa\Controllers\OrderController;
use Guinhawa\Controllers\PartnerController;
use Guinhawa\Controllers\ProductController;
use Guinhawa\Controllers\SalesController;
use Guinhawa\Controllers\SettingsController;
use Guinhawa\Controllers\StateController;
use Guinhawa\Controllers\StockController;
use Guinhawa\Cors;
use Guinhawa\Http;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// Same-origin XAMPP gets no CORS headers; split hosting opts into exact origins.
Cors::apply($config['cors']['allowed_origins'] ?? []);

try {
    $method = Http::method();
    $path = Http::path();

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // Every mutation except the initial sign-in must carry the session CSRF
    // token. Login itself has no authenticated session to protect yet.
    if (!($method === 'POST' && $path === '/auth/login')) {
        Auth::requireCsrf();
    }

    $routes = [
        // Authentication
        ['POST', '#^/auth/login$#', static fn () => AuthController::login()],
        ['POST', '#^/auth/logout$#', static fn () => AuthController::logout()],
        ['GET', '#^/auth/me$#', static fn () => AuthController::me()],
        ['PUT', '#^/auth/profile$#', static fn () => AuthController::updateProfile()],
        ['POST', '#^/auth/change-password$#', static fn () => AuthController::changePassword()],
        ['GET', '#^/auth/email-verification$#', static fn () => AuthController::accountEmailVerificationStatus()],
        ['POST', '#^/auth/email-verification/request$#', static fn () => AuthController::requestAccountEmailVerification()],
        ['POST', '#^/auth/email-verification/verify$#', static fn () => AuthController::verifyAccountEmail()],

        // Email OTP password recovery (Admin, Inventory Staff, Cashier)
        ['POST', '#^/auth/forgot-password$#', static fn () => AuthController::requestPasswordOtp()],
        ['POST', '#^/auth/verify-otp$#', static fn () => AuthController::verifyPasswordOtp()],
        ['POST', '#^/auth/reset-password$#', static fn () => AuthController::completePasswordReset()],

        // Staff activation link
        ['POST', '#^/auth/activate$#', static fn () => AuthController::activateStaff()],

        // Staff password recovery (pre-login, legacy admin-issued one-time code)
        ['POST', '#^/auth/staff/forgot-password$#', static fn () => AuthController::requestStaffReset()],
        ['POST', '#^/auth/staff/verify-code$#', static fn () => AuthController::verifyStaffResetCode()],
        ['POST', '#^/auth/staff/reset-password$#', static fn () => AuthController::completeStaffReset()],

        // Staff directory and administrator employee management
        ['GET', '#^/employees/directory$#', static fn () => EmployeeController::directory()],
        ['GET', '#^/admin/employees$#', static fn () => EmployeeController::index()],
        ['GET', '#^/admin/employees/archived$#', static fn () => EmployeeController::archived()],
        ['POST', '#^/admin/employees$#', static fn () => EmployeeController::store()],
        ['PUT', '#^/admin/employees/(\d+)$#', static fn ($m) => EmployeeController::update((int) $m[1])],
        ['PATCH', '#^/admin/employees/(\d+)/status$#', static fn ($m) => EmployeeController::setStatus((int) $m[1])],
        ['POST', '#^/admin/employees/(\d+)/reset-password$#', static fn ($m) => EmployeeController::resetPassword((int) $m[1])],
        ['POST', '#^/admin/employees/(\d+)/restore$#', static fn ($m) => EmployeeController::restore((int) $m[1])],
        ['POST', '#^/admin/employees/(\d+)/resend-activation$#', static fn ($m) => EmployeeController::resendActivation((int) $m[1])],
        ['DELETE', '#^/admin/employees/(\d+)/activation$#', static fn ($m) => EmployeeController::cancelActivation((int) $m[1])],
        ['DELETE', '#^/admin/employees/(\d+)$#', static fn ($m) => EmployeeController::destroy((int) $m[1])],
        ['GET', '#^/admin/employees/audit/logs$#', static fn () => EmployeeController::auditLogs()],

        // Accounts waiting for their emailed activation link
        ['GET', '#^/admin/activations$#', static fn () => EmployeeController::pendingActivations()],

        // Pending staff password reset requests
        ['GET', '#^/admin/password-reset-requests$#', static fn () => EmployeeController::passwordResetRequests()],
        ['POST', '#^/admin/password-reset-requests/(\d+)/issue$#', static fn ($m) => EmployeeController::issuePasswordResetCode((int) $m[1])],
        ['DELETE', '#^/admin/password-reset-requests/(\d+)$#', static fn ($m) => EmployeeController::dismissPasswordResetRequest((int) $m[1])],

        // Whole-system state
        ['GET', '#^/state$#', static fn () => StateController::index()],

        // Products and inventory
        ['GET', '#^/products$#', static fn () => ProductController::index()],
        ['POST', '#^/products$#', static fn () => ProductController::store()],
        ['PUT', '#^/products/(\d+)$#', static fn ($m) => ProductController::update((int) $m[1])],
        ['DELETE', '#^/products/(\d+)$#', static fn ($m) => ProductController::archive((int) $m[1])],
        ['POST', '#^/products/(\d+)/restore$#', static fn ($m) => ProductController::restore((int) $m[1])],
        ['POST', '#^/stock/in$#', static fn () => StockController::stockIn()],
        ['POST', '#^/stock/out$#', static fn () => StockController::stockOut()],
        ['POST', '#^/suppliers/deliveries$#', static fn () => StockController::delivery()],

        // Suppliers and customers
        ['POST', '#^/suppliers$#', static fn () => PartnerController::addSupplier()],
        ['PUT', '#^/suppliers/([A-Za-z0-9-]+)$#', static fn ($m) => PartnerController::updateSupplier($m[1])],
        ['DELETE', '#^/suppliers/([A-Za-z0-9-]+)$#', static fn ($m) => PartnerController::deleteSupplier($m[1])],
        ['POST', '#^/customers$#', static fn () => PartnerController::addCustomer()],
        ['PUT', '#^/customers/([A-Za-z0-9-]+)$#', static fn ($m) => PartnerController::updateCustomer($m[1])],
        ['DELETE', '#^/customers/([A-Za-z0-9-]+)$#', static fn ($m) => PartnerController::deleteCustomer($m[1])],

        // Orders, POS, held carts
        ['PATCH', '#^/orders/(\d+)/status$#', static fn ($m) => OrderController::setStatus((int) $m[1])],
        ['POST', '#^/sales/checkout$#', static fn () => SalesController::checkout()],
        ['PATCH', '#^/sales/(\d+)/status$#', static fn ($m) => SalesController::setStatus((int) $m[1])],
        ['POST', '#^/sales/held$#', static fn () => SalesController::hold()],
        ['DELETE', '#^/sales/held/(\d+)$#', static fn ($m) => SalesController::removeHeld((int) $m[1])],

        // Attendance and settings
        ['POST', '#^/attendance/clock-in$#', static fn () => AttendanceController::clockIn()],
        ['POST', '#^/attendance/clock-out$#', static fn () => AttendanceController::clockOut()],
        ['PUT', '#^/attendance/record$#', static fn () => AttendanceController::upsert()],
        ['PUT', '#^/settings$#', static fn () => SettingsController::update()],

        // Admin email delivery settings (Gmail SMTP)
        ['GET', '#^/settings/mail$#', static fn () => MailSettingsController::show()],
        ['PUT', '#^/settings/mail$#', static fn () => MailSettingsController::update()],
        ['POST', '#^/settings/mail/test$#', static fn () => MailSettingsController::test()],

        // Appearance preferences (scoped to the signed-in account)
        ['GET', '#^/appearance$#', static fn () => AppearanceController::show()],
        ['PUT', '#^/appearance$#', static fn () => AppearanceController::update()],
        ['DELETE', '#^/appearance$#', static fn () => AppearanceController::destroy()],
    ];

    foreach ($routes as [$allowedMethod, $pattern, $handler]) {
        if ($method !== $allowedMethod) {
            continue;
        }
        if (preg_match($pattern, $path, $matches)) {
            $handler($matches);
            Http::error('No response was produced.', 500, 'SERVER_ERROR');
        }
    }

    Http::error('API endpoint not found.', 404, 'NOT_FOUND');
} catch (Throwable $error) {
    Http::handle($error);
}
