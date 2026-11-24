<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Credential verification and sign-in auditing.
 *
 * Both the PHP-rendered login page and the existing JSON API use this class so
 * a password can never bypass the active-account, lockout, temporary-password
 * or role checks. Admin and staff now share the unified `users` table.
 */
final class LoginService
{
    public const MAX_FAILURES = 5;
    public const LOCK_MINUTES = 5;

    /**
     * @return array{user?: array, error?: string, retry_after?: int}
     */
    public static function authenticate(string $identifier, string $password): array
    {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return ['error' => 'Enter your email address or username and password.'];
        }

        $wait = Auth::tooManyAttempts();
        if ($wait > 0) {
            return [
                'error' => 'Too many sign-in attempts. Try again in ' . self::secondsLabel($wait) . '.',
                'retry_after' => $wait,
            ];
        }

        $row = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . '
             WHERE status <> ?
               AND (
                    username = ?
                 OR (LOWER(email) = ? AND (email_verified = 1 OR role <> ?))
               )
             ORDER BY role = ? DESC
             LIMIT 1',
            [
                'archived',
                strtolower($identifier),
                strtolower($identifier),
                'admin',
                'admin',
            ],
        );

        if ($row) {
            return self::attempt($row, $identifier, $password);
        }

        Auth::recordLoginFailure();
        Auth::audit(null, 'Sign-in Failed', 'An unknown email address or username was used.');
        return ['error' => 'Incorrect email or password. Please try again.'];
    }

    /**
     * @return array{user?: array, error?: string, retry_after?: int}
     */
    private static function attempt(array $row, string $identifier, string $password): array
    {
        $id = (int) $row['id'];
        $type = Users::sessionType($row);
        $table = Schema::t('users');
        $label = $type === 'admin' ? 'Administrator' : 'Employee';
        $code = (string) ($row['employee_code'] ?? 'ADMIN-01');

        if (!empty($row['locked_until'])) {
            $lockedUntil = strtotime((string) $row['locked_until']);
            if ($lockedUntil !== false && $lockedUntil > time()) {
                $wait = $lockedUntil - time();
                return [
                    'error' => 'Too many sign-in attempts. Try again in ' . self::secondsLabel($wait) . '.',
                    'retry_after' => $wait,
                ];
            }
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
            self::recordFailure($id, $row, $type, $code);
            return ['error' => 'Incorrect email or password. Please try again.'];
        }

        if ($type === 'employee' && !empty($row['deleted_at'])) {
            Auth::audit(
                Users::mapEmployee($row),
                'Sign-in Blocked - Archived Account',
                'A staff account kept in Archived staff attempted to sign in.',
                $code,
            );

            return ['error' => 'This account has been deleted. Contact the Administrator.'];
        }

        if ($type === 'employee' && ($row['status'] ?? '') !== 'active') {
            $status = (string) ($row['status'] ?? '');
            Auth::audit(
                Users::mapEmployee($row),
                $status === 'pending' ? 'Sign-in Blocked - Pending' : 'Sign-in Blocked',
                $status === 'pending'
                    ? 'A pending staff account attempted to sign in.'
                    : 'A deactivated employee account attempted to sign in.',
                $code,
            );

            return [
                'error' => $status === 'pending'
                    ? 'This account is pending. Contact the Administrator to activate it.'
                    : 'This account has been deactivated. Contact the Administrator.',
            ];
        }

        if (Auth::temporaryPasswordExpired($row)) {
            Auth::audit(
                Users::mapUser($row),
                'Temporary Password Expired',
                'A sign-in was blocked because the 24-hour temporary password had expired.',
                $code,
            );
            return ['error' => 'Your temporary password has expired. Ask the Administrator to reset it.'];
        }

        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
            Database::execute(
                "UPDATE {$table} SET password_hash = ? WHERE id = ?",
                [password_hash($password, PASSWORD_DEFAULT), $id],
            );
        }

        Database::execute(
            "UPDATE {$table}
             SET last_login_at = NOW(), failed_login_count = 0, locked_until = NULL
             WHERE id = ?",
            [$id],
        );
        $row['last_login_at'] = date('Y-m-d H:i:s');
        $row['failed_login_count'] = 0;
        $row['locked_until'] = null;

        $user = $type === 'admin' ? Auth::loginAdmin($row) : Auth::loginEmployee($row);

        Auth::audit(
            $user,
            $label . ' Signed In',
            $label . ' signed in successfully.',
            $code,
        );

        return ['user' => $user];
    }

    private static function recordFailure(int $id, array $row, string $type, string $code): void
    {
        Database::execute(
            'UPDATE ' . Schema::t('users') . '
             SET failed_login_count = failed_login_count + 1,
                 locked_until = CASE
                     WHEN failed_login_count + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                     ELSE locked_until
                 END
             WHERE id = ?',
            [self::MAX_FAILURES, self::LOCK_MINUTES, $id],
        );

        Auth::audit(
            Users::mapUser($row),
            'Sign-in Failed',
            'An incorrect password was supplied for an existing account.',
            $code,
        );

        Auth::recordLoginFailure();
    }

    public static function secondsLabel(int $seconds): string
    {
        if ($seconds >= 120) {
            return (string) max(1, (int) ceil($seconds / 60)) . ' minutes';
        }
        if ($seconds > 60) {
            return '1 minute';
        }
        return max(1, $seconds) . ' seconds';
    }
}
