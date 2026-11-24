<?php

declare(strict_types=1);

namespace Guinhawa;

use PDO;

final class Auth
{
    public const REMEMBER_DAYS = 30;

    private static ?array $user = null;
    private static bool $resolved = false;
    private static array $config = [];

    public static function boot(array $config): void
    {
        self::$config = $config;

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($config['name']);

        // Split hosting (Vercel frontend + this API on another domain) needs
        // SameSite=None + Secure. Same-origin XAMPP keeps the default Lax.
        $sameSite = $config['same_site'] ?? 'Lax';
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }

        $secureSetting = $config['secure'] ?? '';
        $secure = $secureSetting === ''
            ? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            : filter_var($secureSetting, FILTER_VALIDATE_BOOL);
        if ($sameSite === 'None') {
            // Browsers reject SameSite=None cookies that are not Secure.
            $secure = true;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) (int) $config['lifetime']);

        session_set_cookie_params([
            'lifetime' => $config['lifetime'],
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
        session_start();
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $account = $_SESSION['account'] ?? null;
        if (!is_array($account) || empty($account['type']) || empty($account['id'])) {
            if (!self::attemptRememberLogin()) {
                return null;
            }
            $account = $_SESSION['account'] ?? null;
            if (!is_array($account)) {
                return null;
            }
        }

        $type = (string) $account['type'];
        $id = (int) $account['id'];
        $sessionVersion = (int) ($_SESSION['auth_version'] ?? 0);

        if (!self::sessionIsActive($type, $id)) {
            self::logout();
            return null;
        }

        $row = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [$id],
        );
        if (!$row) {
            self::logout();
            return null;
        }

        $rowType = Users::sessionType($row);
        if ($rowType !== $type) {
            self::logout();
            return null;
        }
        if ($type === 'employee' && ($row['status'] ?? '') !== 'active') {
            self::logout();
            return null;
        }
        if (!empty($row['deleted_at'])) {
            self::logout();
            return null;
        }
        if ((int) ($row['session_version'] ?? 1) !== $sessionVersion) {
            self::logout();
            return null;
        }
        if (self::temporaryPasswordExpired($row)) {
            self::logout();
            return null;
        }

        self::$user = Users::mapUser($row);
        return self::$user;
    }

    public static function requireUser(bool $allowPasswordChange = false): array
    {
        $user = self::user();
        if (!$user) {
            throw new HttpException('Please sign in to continue.', 401, 'UNAUTHENTICATED');
        }
        if (!$allowPasswordChange && !empty($user['mustChangePassword'])) {
            throw new HttpException('Change your temporary password before continuing.', 403, 'PASSWORD_CHANGE_REQUIRED');
        }
        return $user;
    }

    public static function requireAdmin(bool $allowPasswordChange = false): array
    {
        $user = self::requireUser($allowPasswordChange);
        if ($user['role'] !== 'admin') {
            throw new HttpException('Administrator access is required for that action.', 403, 'FORBIDDEN');
        }
        return $user;
    }

    /** @param string[] $roles */
    public static function requireRole(array $roles, bool $allowPasswordChange = false): array
    {
        $user = self::requireUser($allowPasswordChange);
        if (!in_array($user['role'], $roles, true)) {
            throw new HttpException('Your account cannot use that feature.', 403, 'FORBIDDEN');
        }
        return $user;
    }

    public static function loginAdmin(array $row): array
    {
        return self::startSession('admin', $row);
    }

    public static function loginEmployee(array $row): array
    {
        return self::startSession('employee', $row);
    }

    private static function startSession(string $type, array $row): array
    {
        session_regenerate_id(true);

        $version = (int) ($row['session_version'] ?? 1);
        $_SESSION['account'] = ['type' => $type, 'id' => (int) $row['id']];
        $_SESSION['auth_version'] = $version;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['auth_session_hash'] = self::currentSessionHash();
        $_SESSION['auth_last_seen'] = time();

        // Live session validity is the PHP session plus `users.session_version`.
        // Historical login rows remain archived in `users.session_history`.
        self::clearLoginFailures();
        self::$user = null;
        self::$resolved = false;

        return Users::mapUser($row);
    }

    public static function logout(): void
    {
        self::forgetRememberToken();

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        self::$user = null;
        self::$resolved = true;
    }

    /**
     * Bump the account's session version and clear every persistent token.
     * When $keepCurrent is true the session making the request is re-issued
     * with the new version so the user can finish the password change.
     */
    public static function invalidateSessions(string $type, int $id, bool $keepCurrent = false): int
    {
        $table = Schema::t('users');
        Database::execute("UPDATE {$table} SET session_version = session_version + 1 WHERE id = ?", [$id]);
        $row = Database::fetchOne("SELECT session_version FROM {$table} WHERE id = ? LIMIT 1", [$id]);
        $version = (int) ($row['session_version'] ?? 1);

        try {
            Database::execute(
                "UPDATE {$table}
                 SET remember_selector = NULL, remember_token_hash = NULL,
                     remember_session_version = NULL, remember_expires_at = NULL
                 WHERE id = ?",
                [$id],
            );
        } catch (\Throwable) {
            // Never let remember-token bookkeeping block a password change.
        }

        $account = $_SESSION['account'] ?? null;
        if (
            $keepCurrent
            && is_array($account)
            && ($account['type'] ?? '') === $type
            && (int) ($account['id'] ?? 0) === $id
        ) {
            $_SESSION['auth_version'] = $version;
            $_SESSION['auth_session_hash'] = self::currentSessionHash();
            $_SESSION['auth_last_seen'] = time();
        }

        return $version;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Issue a persistent "keep me signed in" token for the current session. */
    public static function rememberCurrentSession(): bool
    {
        $account = $_SESSION['account'] ?? null;
        if (!is_array($account) || empty($account['type']) || empty($account['id'])) {
            return false;
        }

        $id = (int) $account['id'];
        $row = Database::fetchOne(
            'SELECT session_version FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [$id],
        );
        if (!$row) {
            return false;
        }

        return self::issueRememberToken((int) ($row['session_version'] ?? 1), $id);
    }

    private static function attemptRememberLogin(): bool
    {
        $raw = $_COOKIE[self::rememberCookieName()] ?? '';
        if (!is_string($raw) || !str_contains($raw, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', $raw, 2);
        if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            self::forgetRememberToken();
            return false;
        }

        try {
            $row = Database::fetchOne(
                'SELECT * FROM ' . Schema::t('users') . '
                 WHERE remember_selector = ? AND remember_expires_at > NOW() LIMIT 1',
                [$selector],
            );
        } catch (\Throwable) {
            return false;
        }

        if (!$row || !hash_equals((string) $row['remember_token_hash'], hash('sha256', $validator))) {
            self::forgetRememberToken();
            return false;
        }

        $id = (int) $row['id'];
        $type = Users::sessionType($row);
        $revoke = static function () use ($id): void {
            try {
                Database::execute(
                    'UPDATE ' . Schema::t('users') . '
                     SET remember_selector = NULL, remember_token_hash = NULL,
                         remember_session_version = NULL, remember_expires_at = NULL
                     WHERE id = ?',
                    [$id],
                );
            } catch (\Throwable) {
            }
        };

        if ($type === 'employee' && ($row['status'] ?? '') !== 'active') {
            $revoke();
            self::forgetRememberToken();
            return false;
        }
        if (!empty($row['deleted_at'])) {
            $revoke();
            self::forgetRememberToken();
            return false;
        }
        if ((int) ($row['remember_session_version'] ?? 0) !== (int) ($row['session_version'] ?? 1)) {
            $revoke();
            self::forgetRememberToken();
            return false;
        }
        if (self::temporaryPasswordExpired($row)) {
            $revoke();
            self::forgetRememberToken();
            return false;
        }

        // Rotate the token on every automatic sign-in.
        $revoke();
        self::startSession($type, $row);
        self::issueRememberToken((int) ($row['session_version'] ?? 1), $id);

        return true;
    }

    private static function issueRememberToken(int $version, int $id): bool
    {
        try {
            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + self::REMEMBER_DAYS * 86400);

            Database::execute(
                'UPDATE ' . Schema::t('users') . '
                 SET remember_selector = ?, remember_token_hash = ?,
                     remember_session_version = ?, remember_expires_at = ?
                 WHERE id = ?',
                [
                    $selector,
                    hash('sha256', $validator),
                    $version,
                    $expiresAt,
                    $id,
                ],
            );

            self::writeRememberCookie($selector . ':' . $validator, time() + self::REMEMBER_DAYS * 86400);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function forgetRememberToken(): void
    {
        $raw = $_COOKIE[self::rememberCookieName()] ?? '';
        if (is_string($raw) && str_contains($raw, ':')) {
            [$selector] = explode(':', $raw, 2);
            if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
                try {
                    Database::execute(
                        'UPDATE ' . Schema::t('users') . '
                         SET remember_selector = NULL, remember_token_hash = NULL,
                             remember_session_version = NULL, remember_expires_at = NULL
                         WHERE remember_selector = ?',
                        [$selector],
                    );
                } catch (\Throwable) {
                }
            }
        }

        self::writeRememberCookie('', time() - 42000);
        unset($_COOKIE[self::rememberCookieName()]);
    }

    private static function rememberCookieName(): string
    {
        return (self::$config['name'] ?? 'guinhawa_session') . '_remember';
    }

    private static function writeRememberCookie(string $value, int $expires): void
    {
        $secureSetting = self::$config['secure'] ?? '';
        $secure = $secureSetting === ''
            ? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            : filter_var($secureSetting, FILTER_VALIDATE_BOOL);

        setcookie(self::rememberCookieName(), $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function requireCsrf(): void
    {
        if (in_array(Http::method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (!self::user()) {
            return; // login itself has no session yet
        }

        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($provided) || $provided === '' || !hash_equals(self::csrfToken(), $provided)) {
            throw new HttpException('Your session token expired. Refresh the page and try again.', 403, 'CSRF');
        }
    }

    public static function tooManyAttempts(): int
    {
        $until = (int) ($_SESSION['login_blocked_until'] ?? 0);
        if ($until > time()) {
            return $until - time();
        }
        if ($until > 0) {
            unset($_SESSION['login_blocked_until']);
        }
        return 0;
    }

    public static function recordLoginFailure(): void
    {
        $failures = (int) ($_SESSION['login_failures'] ?? 0) + 1;
        $_SESSION['login_failures'] = $failures;
        if ($failures >= 5) {
            $_SESSION['login_blocked_until'] = time() + 300;
            $_SESSION['login_failures'] = 0;
        }
    }

    public static function clearLoginFailures(): void
    {
        unset($_SESSION['login_failures'], $_SESSION['login_blocked_until']);
    }

    public static function currentSessionHash(): string
    {
        return hash('sha256', session_id() . '|' . self::sessionSeed());
    }

    public static function temporaryPasswordExpired(array $row): bool
    {
        if (empty($row['must_change_password'])) {
            return false;
        }
        $expires = $row['temp_password_expires_at'] ?? null;
        if (empty($expires)) {
            return false; // Legacy accounts only carry the change flag.
        }
        $timestamp = strtotime((string) $expires);
        return $timestamp !== false && $timestamp < time();
    }

    /** Account/security audit row in the unified audit log. */
    public static function audit(?array $actor, string $action, string $details, ?string $target = null): void
    {
        Database::execute(
            'INSERT INTO ' . Schema::t('audit_logs') . '
                (category, actor, actor_type, actor_id, actor_name, action, target_employee_id, details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'account',
                $actor['name'] ?? 'System',
                $actor['type'] ?? null,
                self::actorId($actor),
                $actor['name'] ?? 'System',
                $action,
                $target,
                $details,
            ],
        );
    }

    /** Business audit row in the unified audit log. */
    public static function businessAudit(string $action, string $details, ?array $actor = null): void
    {
        $actor ??= self::user();
        Database::execute(
            'INSERT INTO ' . Schema::t('audit_logs') . '
                (category, actor, actor_type, actor_id, actor_name, action, details)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                'business',
                $actor['name'] ?? 'System',
                $actor['type'] ?? null,
                self::actorId($actor),
                $actor['name'] ?? 'System',
                $action,
                $details,
            ],
        );
    }

    private static function actorId(?array $actor): ?int
    {
        if (!is_array($actor) || empty($actor['id'])) {
            return null;
        }
        $id = (int) $actor['id'];
        $row = Database::fetchOne(
            'SELECT id FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [$id],
        );
        return $row ? $id : null;
    }

    public static function pdo(): PDO
    {
        return Database::pdo();
    }

    private static function sessionSeed(): string
    {
        return (string) (self::$config['name'] ?? 'guinhawa_session');
    }

    private static function sessionIsActive(string $type, int $id): bool
    {
        $hash = (string) ($_SESSION['auth_session_hash'] ?? '');
        return $hash !== '' && hash_equals(self::currentSessionHash(), $hash);
    }
}
