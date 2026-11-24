<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Credential verification and sign-in auditing.
 *
 * Both the PHP-rendered login page and the existing JSON API use this class so
 * a password can never bypass the active-account, lockout, temporary-password
 * or role checks.
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
            return ['error' => 'Enter your email address, Staff ID, or username and password.'];
        }

        $wait = Auth::tooManyAttempts();
        if ($wait > 0) {
            return [
                'error' => 'Too many sign-in attempts. Try again in ' . self::secondsLabel($wait) . '.',
                'retry_after' => $wait,
            ];
        }

        $admin = Database::fetchOne(
            'SELECT * FROM admin
             WHERE username = ?
                OR (id = 1 AND ? = "ADMIN-01")
                OR (LOWER(email) = ? AND email_verified = 1)
             LIMIT 1',
            [strtolower($identifier), strtoupper($identifier), strtolower($identifier)],
        );

        if ($admin) {
            return self::attempt($admin, 'admin', $identifier, $password);
        }

        $employee = Database::fetchOne(
            'SELECT * FROM employees
             WHERE username = ? OR employee_id = ? OR staff_id = ?
                OR LOWER(email) = ?
             LIMIT 1',
            [strtolower($identifier), strtoupper($identifier), strtoupper($identifier), strtolower($identifier)],
        );

        if ($employee) {
            return self::attempt($employee, 'employee', $identifier, $password);
        }

        Auth::recordLoginFailure();
        Auth::audit(null, 'Sign-in Failed', 'An unknown email address, Staff ID, or username was used.');
        return ['error' => 'Incorrect email or password. Please try again.'];
    }

    /**
     * @return array{user?: array, error?: string, retry_after?: int}
     */
    private static function attempt(array $row, string $type, string $identifier, string $password): array
    {
        $id = (int) $row['id'];
        $table = $type === 'admin' ? 'admin' : 'employees';
        $label = $type === 'admin' ? 'Administrator' : 'Employee';

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
            self::recordFailure($table, $id, $row, $type);
            return ['error' => 'Incorrect email or password. Please try again.'];
        }

        if ($type === 'employee' && !empty($row['deleted_at'])) {
            Auth::audit(
                Users::mapEmployee($row),
                'Sign-in Blocked - Archived Account',
                'A staff account kept in Archived staff attempted to sign in.',
                (string) $row['employee_id'],
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
                (string) $row['employee_id'],
            );

            return [
                'error' => $status === 'pending'
                    ? 'This account is pending. Contact the Administrator to activate it.'
                    : 'This account has been deactivated. Contact the Administrator.',
            ];
        }

        if (Auth::temporaryPasswordExpired($row)) {
            Auth::audit(
                $type === 'admin' ? Users::mapAdmin($row) : Users::mapEmployee($row),
                'Temporary Password Expired',
                'A sign-in was blocked because the 24-hour temporary password had expired.',
                (string) ($row['employee_id'] ?? 'ADMIN-01'),
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
            ($type === 'admin' ? 'Administrator' : 'Employee') . ' signed in successfully.',
            (string) ($row['employee_id'] ?? 'ADMIN-01'),
        );

        return ['user' => $user];
    }

    private static function recordFailure(string $table, int $id, array $row, string $type): void
    {
        Database::execute(
            "UPDATE {$table}
             SET failed_login_count = failed_login_count + 1,
                 locked_until = CASE
                     WHEN failed_login_count + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                     ELSE locked_until
                 END
             WHERE id = ?",
            [self::MAX_FAILURES, self::LOCK_MINUTES, $id],
        );

        $actor = $type === 'admin' ? Users::mapAdmin($row) : Users::mapEmployee($row);
        Auth::audit(
            $actor,
            'Sign-in Failed',
            'An incorrect password was supplied for an existing account.',
            (string) ($row['employee_id'] ?? 'ADMIN-01'),
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
