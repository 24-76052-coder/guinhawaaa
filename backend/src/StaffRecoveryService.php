<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Staff password recovery without email delivery.
 *
 * 1. A staff member files a request from the sign-in page.
 * 2. An Administrator sees the pending request in User Management and issues a
 *    one-time code, which is shown to the Administrator exactly once.
 * 3. The staff member exchanges the code for a short-lived reset token and sets
 *    a new password.
 *
 * Codes and tokens are only ever stored as hashes. Requests live in the unified
 * `password_reset_tokens` table with channel = 'recovery_code'.
 */
final class StaffRecoveryService
{
    public const CODE_TTL_SECONDS = 3600;
    public const TOKEN_TTL_SECONDS = 900;
    public const REQUEST_COOLDOWN_SECONDS = 60;
    public const MAX_REQUESTS_PER_WINDOW = 5;
    public const REQUEST_WINDOW_SECONDS = 900;
    public const MAX_CODE_ATTEMPTS = 5;

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const GENERIC_MESSAGE = 'If that account exists and is active, your request has been sent to the Administrator for a one-time reset code.';

    /**
     * File a reset request. The response is deliberately identical for unknown,
     * inactive and throttled accounts so the form cannot be used to discover
     * which usernames exist.
     *
     * @return array{ok: bool, message: string}
     */
    public static function request(string $identifier, string $ip = ''): array
    {
        $identifier = trim($identifier);
        $generic = ['ok' => true, 'message' => self::GENERIC_MESSAGE];

        if ($identifier === '') {
            return $generic;
        }

        // Per-browser and per-IP throttles, mirroring the Admin recovery flow.
        $windowStart = (int) ($_SESSION['staff_recovery_window_start'] ?? 0);
        if ($windowStart === 0 || time() - $windowStart > self::REQUEST_WINDOW_SECONDS) {
            $_SESSION['staff_recovery_window_start'] = time();
            $_SESSION['staff_recovery_window_count'] = 0;
        }
        $sessionCount = (int) ($_SESSION['staff_recovery_window_count'] ?? 0);
        $_SESSION['staff_recovery_window_count'] = $sessionCount + 1;

        if ($sessionCount >= self::MAX_REQUESTS_PER_WINDOW) {
            return $generic;
        }

        $ipCount = Database::fetchOne(
            'SELECT COUNT(*) AS total FROM ' . Schema::t('password_reset_tokens') . '
             WHERE requested_ip = ? AND channel = "recovery_code"
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [substr($ip, 0, 45), self::REQUEST_WINDOW_SECONDS],
        );
        if ((int) ($ipCount['total'] ?? 0) >= self::MAX_REQUESTS_PER_WINDOW * 2) {
            return $generic;
        }

        $employee = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . '
             WHERE status = "active" AND deleted_at IS NULL
               AND (username = ? OR employee_code = ? OR (email_verified = 1 AND email = ?))
             LIMIT 1',
            [$identifier, $identifier, $identifier],
        );

        if (!$employee || Users::sessionType($employee) !== 'employee') {
            // Keep the timing roughly constant for unknown accounts.
            usleep(random_int(120000, 260000));
            return $generic;
        }

        $employeeId = (int) $employee['id'];

        // Do not stack duplicate requests while one is still open.
        $open = Database::fetchOne(
            'SELECT id FROM ' . Schema::t('password_reset_tokens') . '
             WHERE channel = "recovery_code" AND user_id = ?
               AND consumed_at IS NULL AND reset_completed_at IS NULL
               AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1',
            [$employeeId],
        );

        if ($open) {
            $createdAt = Database::fetchOne(
                'SELECT created_at FROM ' . Schema::t('password_reset_tokens') . ' WHERE id = ? LIMIT 1',
                [(int) $open['id']],
            );
            if ($createdAt && strtotime((string) $createdAt['created_at']) > time() - self::REQUEST_COOLDOWN_SECONDS) {
                return $generic;
            }
            // An older request is already waiting; do not create a second one.
            return $generic;
        }

        Database::execute(
            'INSERT INTO ' . Schema::t('password_reset_tokens') . '
                (user_id, email, channel, expires_at, requested_ip)
             VALUES (?, ?, "recovery_code", ?, ?)',
            [
                $employeeId,
                (string) ($employee['email'] ?? ''),
                date('Y-m-d H:i:s', time() + self::CODE_TTL_SECONDS),
                substr($ip, 0, 45),
            ],
        );

        Auth::audit(
            ['type' => 'employee', 'id' => $employeeId, 'name' => (string) $employee['full_name']],
            'Staff Password Reset Requested',
            "{$employee['full_name']} ({$employee['employee_code']}) requested a password reset code.",
            (string) $employee['employee_code'],
        );

        return $generic;
    }

    /**
     * Requests waiting for an Administrator to issue (or re-issue) a code.
     *
     * @return list<array<string, mixed>>
     */
    public static function pendingRequests(): array
    {
        $rows = Database::fetchAll(
            'SELECT r.id, r.user_id, r.email, r.created_at, r.expires_at, r.attempts,
                    r.otp_hash IS NOT NULL AS issued,
                    u.employee_code, u.username, u.full_name, u.role, u.status
             FROM ' . Schema::t('password_reset_tokens') . ' r
             INNER JOIN ' . Schema::t('users') . ' u ON u.id = r.user_id
             WHERE r.channel = "recovery_code"
               AND r.consumed_at IS NULL
               AND r.reset_completed_at IS NULL
               AND r.expires_at > NOW()
               AND u.deleted_at IS NULL
             ORDER BY r.created_at ASC, r.id ASC',
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'employeeId' => (string) $row['employee_code'],
                'username' => (string) $row['username'],
                'fullName' => (string) $row['full_name'],
                'role' => (string) $row['role'],
                'roleLabel' => Users::ROLE_LABELS[$row['role']] ?? (string) $row['role'],
                'email' => (string) $row['email'],
                'status' => (string) $row['status'],
                'issued' => (bool) $row['issued'],
                'attempts' => (int) $row['attempts'],
                'requestedAt' => (string) $row['created_at'],
                'expiresAt' => (string) $row['expires_at'],
            ];
        }, $rows);
    }

    /**
     * Generate a one-time code. The plaintext code is returned to the caller
     * once and never stored; only its password_hash() value is kept.
     *
     * @return array{code: string, expiresAt: string, request: array<string, mixed>}
     */
    public static function issueCode(int $requestId, array $actor): array
    {
        $row = Database::fetchOne(
            'SELECT r.*, u.employee_code, u.username, u.full_name, u.status
             FROM ' . Schema::t('password_reset_tokens') . ' r
             INNER JOIN ' . Schema::t('users') . ' u ON u.id = r.user_id
             WHERE r.id = ? AND r.channel = "recovery_code" LIMIT 1',
            [$requestId],
        );

        if (!$row) {
            throw new HttpException('That password reset request no longer exists.', 404, 'NOT_FOUND');
        }
        if (!empty($row['consumed_at']) || !empty($row['reset_completed_at'])) {
            throw new HttpException('That request has already been handled.', 409, 'CONFLICT');
        }
        if ($row['status'] !== 'active') {
            throw new HttpException('That account is deactivated. Reactivate it before issuing a reset code.', 409, 'ACCOUNT_INACTIVE');
        }

        $raw = '';
        for ($i = 0; $i < 8; $i += 1) {
            $raw .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }
        $formatted = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        $expiresAt = date('Y-m-d H:i:s', time() + self::CODE_TTL_SECONDS);

        Database::execute(
            'UPDATE ' . Schema::t('password_reset_tokens') . '
             SET otp_hash = ?, expires_at = ?, attempts = 0,
                 reset_token_hash = NULL, token_expires_at = NULL
             WHERE id = ?',
            [password_hash($raw, PASSWORD_DEFAULT), $expiresAt, $requestId],
        );

        Auth::audit(
            $actor,
            'Staff Reset Code Issued',
            "A one-time password reset code was issued for {$row['full_name']} ({$row['employee_code']}).",
            (string) $row['employee_code'],
        );

        return [
            'code' => $formatted,
            'expiresAt' => $expiresAt,
            'request' => [
                'id' => $requestId,
                'employeeId' => (string) $row['employee_code'],
                'username' => (string) $row['username'],
                'fullName' => (string) $row['full_name'],
            ],
        ];
    }

    /** Close a request without issuing a code. */
    public static function dismiss(int $requestId, array $actor): array
    {
        $row = Database::fetchOne(
            'SELECT r.*, u.employee_code, u.full_name
             FROM ' . Schema::t('password_reset_tokens') . ' r
             INNER JOIN ' . Schema::t('users') . ' u ON u.id = r.user_id
             WHERE r.id = ? AND r.channel = "recovery_code" LIMIT 1',
            [$requestId],
        );

        if (!$row) {
            throw new HttpException('That password reset request no longer exists.', 404, 'NOT_FOUND');
        }

        Database::execute(
            'UPDATE ' . Schema::t('password_reset_tokens') . ' SET consumed_at = NOW() WHERE id = ?',
            [$requestId],
        );

        Auth::audit(
            $actor,
            'Staff Reset Request Dismissed',
            "The password reset request from {$row['full_name']} ({$row['employee_code']}) was dismissed.",
            (string) $row['employee_code'],
        );

        return ['dismissed' => true];
    }

    /**
     * Exchange a one-time code for a short-lived reset token. The token is kept
     * in the PHP session; it is never written to the page.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function verifyCode(string $identifier, string $code): array
    {
        $identifier = trim($identifier);
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $code) ?? '');

        if (strlen($normalized) !== 8) {
            return ['ok' => false, 'error' => 'Enter the 8-character reset code from your Administrator.'];
        }

        $request = Database::fetchOne(
            'SELECT r.*, u.status
             FROM ' . Schema::t('password_reset_tokens') . ' r
             INNER JOIN ' . Schema::t('users') . ' u ON u.id = r.user_id
             WHERE r.channel = "recovery_code"
               AND r.otp_hash IS NOT NULL
               AND r.consumed_at IS NULL
               AND r.reset_completed_at IS NULL
               AND (u.username = ? OR u.employee_code = ? OR u.email = ?)
             ORDER BY r.id DESC LIMIT 1',
            [$identifier, $identifier, $identifier],
        );

        if (!$request) {
            return ['ok' => false, 'error' => 'That reset code is invalid or has expired. Ask your Administrator for a new code.'];
        }

        if (strtotime((string) $request['expires_at']) < time()) {
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . ' SET consumed_at = NOW() WHERE id = ?',
                [(int) $request['id']],
            );
            return ['ok' => false, 'error' => 'That reset code has expired. Ask your Administrator for a new code.'];
        }

        if ($request['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This account is deactivated. Contact the Administrator.'];
        }

        if ((int) $request['attempts'] >= (int) $request['max_attempts']) {
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . ' SET consumed_at = NOW() WHERE id = ?',
                [(int) $request['id']],
            );
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Ask your Administrator for a new code.'];
        }

        if (!password_verify($normalized, (string) $request['otp_hash'])) {
            $attempts = (int) $request['attempts'] + 1;
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . '
                 SET attempts = ?, consumed_at = CASE WHEN ? >= max_attempts THEN NOW() ELSE consumed_at END
                 WHERE id = ?',
                [$attempts, $attempts, (int) $request['id']],
            );

            $remaining = max(0, (int) $request['max_attempts'] - $attempts);
            return [
                'ok' => false,
                'error' => $remaining > 0
                    ? 'That reset code is incorrect. ' . $remaining . ' attempt(s) remaining.'
                    : 'Too many incorrect attempts. Ask your Administrator for a new code.',
            ];
        }

        $token = bin2hex(random_bytes(32));
        Database::execute(
            'UPDATE ' . Schema::t('password_reset_tokens') . '
             SET reset_token_hash = ?, token_expires_at = ?, attempts = attempts + 1
             WHERE id = ?',
            [hash('sha256', $token), date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS), (int) $request['id']],
        );

        $_SESSION['staff_reset_token'] = $token;
        $_SESSION['staff_reset_request_id'] = (int) $request['id'];

        Auth::audit(
            ['type' => 'employee', 'id' => (int) $request['user_id'], 'name' => 'Staff member'],
            'Staff Reset Code Verified',
            'A one-time staff password reset code was accepted.',
            null,
        );

        return ['ok' => true];
    }

    /**
     * Complete a reset using the token stored in the session by verifyCode().
     *
     * @return array{ok: bool, username?: string, error?: string}
     */
    public static function completeReset(string $newPassword, string $confirmPassword): array
    {
        $token = (string) ($_SESSION['staff_reset_token'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'Your reset session has expired. Enter your code again.'];
        }

        $request = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('password_reset_tokens') . '
             WHERE channel = "recovery_code" AND reset_token_hash = ?
               AND consumed_at IS NULL AND reset_completed_at IS NULL
               AND token_expires_at > NOW()
             ORDER BY id DESC LIMIT 1',
            [hash('sha256', $token)],
        );

        if (!$request) {
            self::clearSession();
            return ['ok' => false, 'error' => 'Your reset session has expired. Enter your code again.'];
        }

        $employee = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [(int) $request['user_id']],
        );

        if (!$employee || $employee['status'] !== 'active' || Users::sessionType($employee) !== 'employee') {
            self::clearSession();
            return ['ok' => false, 'error' => 'This account is deactivated. Contact the Administrator.'];
        }

        try {
            $newPassword = Validator::password($newPassword, 'New password');
        } catch (HttpException $error) {
            return ['ok' => false, 'error' => $error->getMessage()];
        }

        if ($newPassword !== $confirmPassword) {
            return ['ok' => false, 'error' => 'The password confirmation does not match.'];
        }

        if (password_verify($newPassword, (string) $employee['password_hash'])) {
            return ['ok' => false, 'error' => 'Choose a password that is different from your current password.'];
        }

        $employeeId = (int) $employee['id'];

        Database::transaction(static function () use ($newPassword, $employeeId, $request): void {
            Database::execute(
                'UPDATE ' . Schema::t('users') . '
                 SET password_hash = ?, must_change_password = 0, temp_password_expires_at = NULL,
                     password_changed_at = NOW(), failed_login_count = 0, locked_until = NULL
                 WHERE id = ?',
                [password_hash($newPassword, PASSWORD_DEFAULT), $employeeId],
            );

            Auth::invalidateSessions('employee', $employeeId);

            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . '
                 SET consumed_at = NOW(), reset_completed_at = NOW(), reset_token_hash = NULL
                 WHERE id = ?',
                [(int) $request['id']],
            );
        });

        self::clearSession();

        Auth::audit(
            ['type' => 'employee', 'id' => $employeeId, 'name' => (string) $employee['full_name']],
            'Staff Password Reset Completed',
            "{$employee['full_name']} ({$employee['employee_code']}) set a new password with a one-time reset code.",
            (string) $employee['employee_code'],
        );

        return ['ok' => true, 'username' => (string) $employee['username']];
    }

    private static function clearSession(): void
    {
        unset($_SESSION['staff_reset_token'], $_SESSION['staff_reset_request_id']);
    }
}
