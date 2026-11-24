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
            self::$resolved = true;
        }

        $type = (string) $account['type'];
        $id = (int) $account['id'];
        $sessionVersion = (int) ($_SESSION['auth_version'] ?? 0);

        if (!self::sessionIsActive($type, $id)) {
            self::logout();
            return null;
        }

        if ($type === 'admin') {
            $row = Database::fetchOne('SELECT * FROM admin WHERE id = 1 LIMIT 1');
            if (!$row) {
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
            self::$user = Users::mapAdmin($row);
            self::touchSession($type, $id);
            return self::$user;
        }

        if ($type === 'employee') {
            $row = Database::fetchOne('SELECT * FROM employees WHERE id = ? LIMIT 1', [$id]);
            if (!$row || $row['status'] !== 'active') {
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
            self::$user = Users::mapEmployee($row);
            self::touchSession($type, $id);
            return self::$user;
        }

        self::logout();
        return null;
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

        self::openAuthSession($type, (int) $row['id'], $version, $_SESSION['auth_session_hash']);
        self::clearLoginFailures();
        self::$user = null;
        self::$resolved = false;

        return $type === 'admin' ? Users::mapAdmin($row) : Users::mapEmployee($row);
    }

    public static function logout(): void
    {
        $account = $_SESSION['account'] ?? null;
        $hash = $_SESSION['auth_session_hash'] ?? '';

        self::forgetRememberToken();

        if (is_array($account) && !empty($account['type']) && !empty($account['id'])) {
            try {
                Database::execute(
                    'UPDATE auth_sessions SET revoked_at = NOW()
                     WHERE session_hash = ? AND user_type = ? AND user_id = ? AND revoked_at IS NULL',
                    [$hash, $account['type'], (int) $account['id']],
                );
            } catch (\Throwable) {
                // Never let audit bookkeeping block a sign-out.
            }
        }

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
     * Bump the account's session version and revoke every stored session.
     * When $keepCurrent is true the session making the request is re-issued
     * with the new version so the user can finish the password change.
     */
    public static function invalidateSessions(string $type, int $id, bool $keepCurrent = false): int
    {
        $table = $type === 'admin' ? 'admin' : 'employees';
        Database::execute("UPDATE {$table} SET session_version = session_version + 1 WHERE id = ?", [$id]);
        $row = Database::fetchOne("SELECT session_version FROM {$table} WHERE id = ? LIMIT 1", [$id]);
        $version = (int) ($row['session_version'] ?? 1);

        Database::execute(
            'UPDATE auth_sessions SET revoked_at = NOW()
             WHERE user_type = ? AND user_id = ? AND revoked_at IS NULL',
            [$type, $id],
        );

        // A password reset or password change also revokes persistent
        // "keep me signed in" tokens for that account.
        try {
            Database::execute(
                'DELETE FROM auth_remember_tokens WHERE user_type = ? AND user_id = ?',
                [$type, $id],
            );
        } catch (\Throwable) {
            // Older databases may not have the remember-token table yet.
        }

        $account = $_SESSION['account'] ?? null;
        if (
            $keepCurrent
            && is_array($account)
            && ($account['type'] ?? '') === $type
            && (int) ($account['id'] ?? 0) === $id
        ) {
            $hash = self::currentSessionHash();
            $_SESSION['auth_version'] = $version;
            $_SESSION['auth_session_hash'] = $hash;
            $_SESSION['auth_last_seen'] = time();
            self::openAuthSession($type, $id, $version, $hash);
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

        $type = (string) $account['type'];
        $id = (int) $account['id'];
        $table = $type === 'admin' ? 'admin' : 'employees';
        $row = Database::fetchOne("SELECT session_version FROM {$table} WHERE id = ? LIMIT 1", [$id]);
        if (!$row) {
            return false;
        }

        return self::issueRememberToken($type, $id, (int) ($row['session_version'] ?? 1));
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
                'SELECT * FROM auth_remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1',
                [$selector],
            );
        } catch (\Throwable) {
            return false;
        }

        if (!$row || !hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
            self::forgetRememberToken();
            return false;
        }

        $type = (string) $row['user_type'];
        $id = (int) $row['user_id'];
        $table = $type === 'admin' ? 'admin' : 'employees';
        $account = Database::fetchOne("SELECT * FROM {$table} WHERE id = ? LIMIT 1", [$id]);

        $revoke = static function (int $tokenId): void {
            try {
                Database::execute('DELETE FROM auth_remember_tokens WHERE id = ?', [$tokenId]);
            } catch (\Throwable) {
            }
        };

        if (!$account) {
            $revoke((int) $row['id']);
            self::forgetRememberToken();
            return false;
        }
        if ($type === 'employee' && ($account['status'] ?? '') !== 'active') {
            $revoke((int) $row['id']);
            self::forgetRememberToken();
            return false;
        }
        if ((int) ($row['session_version'] ?? 1) !== (int) ($account['session_version'] ?? 1)) {
            $revoke((int) $row['id']);
            self::forgetRememberToken();
            return false;
        }
        if (self::temporaryPasswordExpired($account)) {
            $revoke((int) $row['id']);
            self::forgetRememberToken();
            return false;
        }

        // Rotate the token on every automatic sign-in.
        $revoke((int) $row['id']);
        self::startSession($type, $account);
        self::issueRememberToken($type, $id, (int) ($account['session_version'] ?? 1));

        return true;
    }

    private static function issueRememberToken(string $type, int $id, int $version): bool
    {
        try {
            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + self::REMEMBER_DAYS * 86400);

            Database::execute(
                'INSERT INTO auth_remember_tokens
                    (selector, token_hash, user_type, user_id, session_version, ip_address, user_agent, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $selector,
                    hash('sha256', $validator),
                    $type,
                    $id,
                    $version,
                    substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    $expiresAt,
                ],
            );

            Database::execute(
                'DELETE FROM auth_remember_tokens WHERE expires_at < NOW()',
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
                    Database::execute('DELETE FROM auth_remember_tokens WHERE selector = ?', [$selector]);
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

    /** Account audit row for administrator activity. */
    public static function audit(?array $actor, string $action, string $details, ?string $target = null): void
    {
        Database::execute(
            'INSERT INTO account_audit_logs (actor_type, actor_id, actor_name, action, target_employee_id, details)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $actor['type'] ?? null,
                isset($actor['id']) ? (int) $actor['id'] : null,
                $actor['name'] ?? 'System',
                $action,
                $target,
                $details,
            ],
        );
    }

    public static function businessAudit(string $action, string $details, ?array $actor = null): void
    {
        $actor ??= self::user();
        Database::execute(
            'INSERT INTO audit_logs (actor, actor_type, actor_id, action, details)
             VALUES (?, ?, ?, ?, ?)',
            [
                $actor['name'] ?? 'System',
                $actor['type'] ?? null,
                isset($actor['id']) ? (int) $actor['id'] : null,
                $action,
                $details,
            ],
        );
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
        if ($hash === '') {
            return false;
        }

        $row = Database::fetchOne(
            'SELECT id FROM auth_sessions
             WHERE session_hash = ? AND user_type = ? AND user_id = ? AND revoked_at IS NULL
             LIMIT 1',
            [$hash, $type, $id],
        );

        return (bool) $row;
    }

    private static function openAuthSession(string $type, int $id, int $version, string $hash): void
    {
        Database::execute(
            'INSERT INTO auth_sessions
                (session_hash, user_type, user_id, session_version, ip_address, user_agent, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                user_type = VALUES(user_type),
                user_id = VALUES(user_id),
                session_version = VALUES(session_version),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                last_seen_at = NOW(),
                revoked_at = NULL',
            [
                $hash,
                $type,
                $id,
                $version,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ],
        );
    }

    private static function touchSession(string $type, int $id): void
    {
        $lastSeen = (int) ($_SESSION['auth_last_seen'] ?? 0);
        if ($lastSeen > 0 && time() - $lastSeen < 300) {
            return;
        }
        $_SESSION['auth_last_seen'] = time();

        Database::execute(
            'UPDATE auth_sessions SET last_seen_at = NOW()
             WHERE session_hash = ? AND user_type = ? AND user_id = ? AND revoked_at IS NULL',
            [self::currentSessionHash(), $type, $id],
        );
    }
}
