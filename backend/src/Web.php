<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Shared helpers for the PHP-rendered authentication pages.
 * These pages use the same session and database as the existing React app.
 */
final class Web
{
    private static string $basePath = '/guinhawa';

    public static function boot(array $config): void
    {
        self::$basePath = rtrim((string) ($config['app_base_path'] ?? '/guinhawa'), '/');

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        header('Cache-Control: no-store, max-age=0');
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(string $path = ''): string
    {
        return self::$basePath . '/' . ltrim($path, '/');
    }

    public static function dashboardUrl(?array $user = null): string
    {
        $user ??= Auth::user();
        $role = (string) ($user['role'] ?? '');

        if ($role === 'admin') {
            return self::url('admin/index.php');
        }
        if ($role === 'inventory-staff') {
            return self::url('inventory/index.php');
        }
        if ($role === 'counter-staff' || $role === 'cashier') {
            return self::url('cashier/index.php');
        }

        return self::url('login/index.php');
    }

    public static function csrfToken(): string
    {
        return Auth::csrfToken();
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::e(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $provided = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($provided) || $provided === '' || !hash_equals(self::csrfToken(), $provided)) {
            self::abort(403, 'Your session expired', 'Refresh the page and submit the form again.');
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['auth_flashes'] ??= [];
        $_SESSION['auth_flashes'][] = ['type' => $type, 'message' => $message];
    }

    /** Browser-facing guidance when the CLI administrator setup has not run. */
    public static function setupRequired(): never
    {
        self::abort(
            503,
            'Setup required',
            'Create the administrator first. Run backend/scripts/setup_admin.php from a terminal, then reload this page.'
        );
    }

    /** @return array<int, array{type: string, message: string}> */
    public static function takeFlashes(): array
    {
        $flashes = $_SESSION['auth_flashes'] ?? [];
        unset($_SESSION['auth_flashes']);
        return is_array($flashes) ? $flashes : [];
    }

    public static function redirect(string $path, int $status = 303): never
    {
        header('Location: ' . (str_starts_with($path, 'http') ? $path : self::url($path)), true, $status);
        exit;
    }

    /** Redirect to an already-resolved URL (for example Web::dashboardUrl()). */
    public static function go(string $url, int $status = 303): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    /**
     * @param string[] $roles Empty means any authenticated role.
     * @param bool $allowTemp When false a temporary-password user is sent to
     *                        the change-password page before anything else.
     */
    public static function requireUser(array $roles = [], bool $allowTemp = false): array
    {
        $user = Auth::user();
        if (!$user) {
            self::redirect('login/index.php?expired=1');
        }

        if (!$allowTemp && !empty($user['mustChangePassword'])) {
            self::redirect('login/change-password.php?required=1');
        }

        if ($roles && !in_array((string) $user['role'], $roles, true)) {
            self::abort(403, 'Access denied', 'Your account does not have permission to open this page.');
        }

        return $user;
    }

    public static function requireAdmin(bool $allowTemp = false): array
    {
        return self::requireUser(['admin'], $allowTemp);
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    public static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function abort(int $status, string $title, string $message): never
    {
        http_response_code($status);
        $titleSafe = self::e($title);
        $messageSafe = self::e($message);
        $login = self::url('login/index.php');
        $logo = self::url('assets/images/guinhawa-logo.png');

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $titleSafe . ' &middot; Guinhawa</title>'
            . '<link rel="icon" type="image/png" href="' . self::e($logo) . '">'
            . '<style>'
            . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
            . 'background:#f3f7f4;color:#1f2933;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;padding:24px}'
            . '.card{width:100%;max-width:460px;background:#fff;border:1px solid #dbe4dc;border-radius:18px;'
            . 'box-shadow:0 18px 40px rgba(22,101,52,.08);padding:40px 32px;text-align:center}'
            . '.badge{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;'
            . 'border-radius:50%;background:#166534;color:#fff;font-weight:700;letter-spacing:.04em;margin-bottom:18px}'
            . '.eyebrow{margin:0 0 6px;text-transform:uppercase;letter-spacing:.14em;font-size:11px;color:#61707d;font-weight:600}'
            . 'h1{margin:0 0 10px;font-size:26px;color:#14532d}'
            . 'p{margin:0 0 24px;color:#475569;line-height:1.55}'
            . 'a{display:inline-block;background:#166534;color:#fff;text-decoration:none;font-weight:600;'
            . 'padding:12px 20px;border-radius:10px}'
            . 'a:hover{background:#14532d}'
            . '</style>'
            . '</head><body><main class="card">'
            . '<div class="badge">GQ</div>'
            . '<p class="eyebrow">Guinhawa Poultry Supply</p>'
            . '<h1>' . $titleSafe . '</h1>'
            . '<p>' . $messageSafe . '</p>'
            . '<a href="' . self::e($login) . '">Back to sign in</a>'
            . '</main></body></html>';
        exit;
    }

    public static function roleLabel(?array $user = null): string
    {
        $user ??= Auth::user();
        return (string) ($user['roleLabel'] ?? 'Account');
    }

    public static function initials(string $name): string
    {
        return Validator::initials($name);
    }

    public static function jsonForScript(mixed $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }
}
