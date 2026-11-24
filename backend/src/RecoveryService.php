<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Password recovery by registered email for every account type.
 *
 *   registered email -> hashed 6-digit OTP (5 minutes, 5 attempts)
 *   -> hashed one-time reset token (10 minutes) -> new password
 *
 * OTPs and reset tokens are only stored as hashes in `password_reset_tokens`.
 * The flow is bound to the browser session that requested it, so a code cannot
 * be replayed from another device. Unknown, inactive and pending accounts
 * receive the same generic response as a valid one.
 */
final class RecoveryService
{
    public const OTP_TTL_SECONDS = 300;
    public const TOKEN_TTL_SECONDS = 600;
    /** Secure emailed reset links stay valid for one hour. */
    public const LINK_TTL_SECONDS = 3600;
    public const RESEND_COOLDOWN = 60;
    public const MAX_OTP_ATTEMPTS = 5;
    public const MAX_REQUESTS_PER_WINDOW = 5;
    public const REQUEST_WINDOW_SECONDS = 900;

    private const GENERIC_MESSAGE
        = 'If that email is registered, a reset code is on its way.';

    /**
     * @return array{ok: bool, message: string, retry_after?: int}
     */
    public static function requestOtp(string $email, string $ip = ''): array
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.'];
        }

        $cooldown = self::resendAvailableIn();
        $ipWait = self::ipCooldownRemaining($ip);
        $wait = max($cooldown, $ipWait);
        if ($wait > 0) {
            return [
                'ok' => false,
                'message' => 'Please wait ' . $wait . ' seconds before requesting another code.',
                'retry_after' => $wait,
            ];
        }

        if (self::tooManyRequests($ip)) {
            return ['ok' => true, 'message' => self::GENERIC_MESSAGE];
        }

        $account = self::findRecoverableAccount($email);
        if (!$account) {
            // Same shape and roughly the same timing for unknown addresses.
            usleep(random_int(120000, 260000));
            return ['ok' => true, 'message' => self::GENERIC_MESSAGE];
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + self::OTP_TTL_SECONDS);
        $linkToken = bin2hex(random_bytes(32));
        $linkExpiresAt = date('Y-m-d H:i:s', time() + self::LINK_TTL_SECONDS);
        $link = self::resetLink($linkToken);

        // Capture the new row id before the transaction commits: MySQL's PDO
        // driver resets lastInsertId() on commit.
        $requestId = Database::transaction(static function () use (
            $account,
            $email,
            $otp,
            $expiresAt,
            $linkToken,
            $linkExpiresAt,
            $ip,
        ): int {
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . '
                 SET consumed_at = NOW()
                 WHERE user_id = ? AND channel = "email"
                   AND consumed_at IS NULL AND reset_completed_at IS NULL',
                [$account['id']],
            );

            Database::execute(
                'INSERT INTO ' . Schema::t('password_reset_tokens') . '
                    (user_id, email, channel, otp_hash, reset_token_hash,
                     expires_at, token_expires_at, requested_ip)
                 VALUES (?, ?, "email", ?, ?, ?, ?, ?)',
                [
                    $account['id'],
                    $email,
                    password_hash($otp, PASSWORD_DEFAULT),
                    hash('sha256', $linkToken),
                    $expiresAt,
                    $linkExpiresAt,
                    substr($ip, 0, 45),
                ],
            );

            return Database::lastId();
        });

        try {
            Mailer::send(
                $email,
                'Reset your Guinhawa password',
                self::otpEmailHtml($otp, $account['roleLabel'], $link),
                self::otpEmailText($otp, $account['roleLabel'], $link),
            );
        } catch (\Throwable $error) {
            error_log('[guinhawa] Password reset email could not be sent: ' . $error->getMessage());
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . ' SET consumed_at = NOW() WHERE id = ?',
                [$requestId],
            );
            // The generic response is deliberate: reporting delivery failure
            // here would reveal whether the address belongs to an account.
            return ['ok' => true, 'message' => self::GENERIC_MESSAGE];
        }

        $_SESSION['auth_pwd_reset_request_id'] = $requestId;
        $_SESSION['auth_pwd_reset_email'] = $email;
        $_SESSION['auth_otp_last_sent'] = time();
        unset($_SESSION['auth_pwd_reset_token']);

        Auth::audit(
            $account['actor'],
            'Password Reset Requested',
            'A 6-digit email verification code was requested for ' . $email . '.',
            $account['code'],
        );

        return ['ok' => true, 'message' => self::GENERIC_MESSAGE];
    }

    public static function resendAvailableIn(): int
    {
        $last = (int) ($_SESSION['auth_otp_last_sent'] ?? 0);
        if ($last <= 0) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN - (time() - $last));
    }

    public static function pendingRequest(): ?array
    {
        $id = (int) ($_SESSION['auth_pwd_reset_request_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM ' . Schema::t('password_reset_tokens') . '
             WHERE id = ? AND channel = "email" AND consumed_at IS NULL AND reset_completed_at IS NULL
             LIMIT 1',
            [$id],
        );
    }

    public static function maskedPendingEmail(): string
    {
        $email = (string) ($_SESSION['auth_pwd_reset_email'] ?? '');
        if ($email === '' || !str_contains($email, '@')) {
            return 'your registered email';
        }
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible . str_repeat('*', max(1, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function verifyOtp(string $code): array
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return ['ok' => false, 'error' => 'Enter the 6-digit code from the email.'];
        }

        $request = self::pendingRequest();
        if (!$request || !$request['otp_hash']) {
            return ['ok' => false, 'error' => 'Request a new verification code.'];
        }

        if (strtotime((string) $request['expires_at']) < time()) {
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . ' SET consumed_at = NOW() WHERE id = ?',
                [(int) $request['id']],
            );
            return ['ok' => false, 'error' => 'That code has expired. Request a new code.'];
        }

        if ((int) $request['attempts'] >= (int) $request['max_attempts']) {
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . ' SET consumed_at = NOW() WHERE id = ?',
                [(int) $request['id']],
            );
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Request a new code.'];
        }

        if (!password_verify($code, (string) $request['otp_hash'])) {
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
                    ? 'That code is incorrect. ' . $remaining . ' attempt(s) remaining.'
                    : 'Too many incorrect attempts. Request a new code.',
            ];
        }

        $token = bin2hex(random_bytes(32));
        $consumed = Database::execute(
            'UPDATE ' . Schema::t('password_reset_tokens') . '
             SET otp_hash = NULL, consumed_at = NOW(), attempts = attempts + 1,
                 reset_token_hash = ?, token_expires_at = ?
             WHERE id = ? AND consumed_at IS NULL',
            [
                hash('sha256', $token),
                date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS),
                (int) $request['id'],
            ],
        );

        if ($consumed !== 1) {
            return ['ok' => false, 'error' => 'That code has already been used. Request a new code.'];
        }

        $_SESSION['auth_pwd_reset_token'] = $token;
        $_SESSION['auth_pwd_reset_request_id'] = (int) $request['id'];

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string, username?: string, roleLabel?: string}
     */
    public static function completeReset(string $newPassword, string $confirmPassword): array
    {
        $token = (string) ($_SESSION['auth_pwd_reset_token'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'Verify the 6-digit code before choosing a new password.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['ok' => false, 'error' => 'The new password confirmation does not match.'];
        }

        try {
            $newPassword = Validator::password($newPassword, 'New password');
        } catch (HttpException $error) {
            return ['ok' => false, 'error' => $error->getMessage()];
        }

        $request = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('password_reset_tokens') . '
             WHERE reset_token_hash = ? AND reset_completed_at IS NULL
             LIMIT 1',
            [hash('sha256', $token)],
        );

        if (!$request) {
            return ['ok' => false, 'error' => 'Your reset session expired. Start the process again.'];
        }
        if (empty($request['token_expires_at']) || strtotime((string) $request['token_expires_at']) < time()) {
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . '
                 SET reset_completed_at = NOW(), reset_token_hash = NULL WHERE id = ?',
                [(int) $request['id']],
            );
            unset($_SESSION['auth_pwd_reset_token']);
            return ['ok' => false, 'error' => 'Your reset session expired. Start the process again.'];
        }

        $account = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [(int) $request['user_id']],
        );

        if (!$account) {
            return ['ok' => false, 'error' => 'That account no longer exists.'];
        }
        if (password_verify($newPassword, (string) $account['password_hash'])) {
            return ['ok' => false, 'error' => 'Choose a password that is different from your current password.'];
        }

        $id = (int) $account['id'];
        $sessionType = Users::sessionType($account);

        Database::transaction(static function () use ($id, $newPassword, $request): void {
            Database::execute(
                'UPDATE ' . Schema::t('users') . '
                 SET password_hash = ?, must_change_password = 0, temp_password_expires_at = NULL,
                     password_changed_at = NOW(), failed_login_count = 0, locked_until = NULL
                 WHERE id = ?',
                [password_hash($newPassword, PASSWORD_DEFAULT), $id],
            );

            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . '
                 SET reset_completed_at = NOW(), reset_token_hash = NULL
                 WHERE id = ?',
                [(int) $request['id']],
            );
        });

        Auth::invalidateSessions($sessionType, $id);

        $actor = Users::mapUser($account);
        Auth::audit(
            $actor,
            'Password Reset',
            $sessionType === 'admin'
                ? 'The Administrator password was reset with an email verification code.'
                : 'A staff password was reset with an email verification code.',
            (string) ($actor['employeeId'] ?? 'ADMIN-01'),
        );

        unset(
            $_SESSION['auth_pwd_reset_request_id'],
            $_SESSION['auth_pwd_reset_email'],
            $_SESSION['auth_pwd_reset_token'],
            $_SESSION['auth_otp_last_sent'],
        );

        return [
            'ok' => true,
            'username' => (string) ($account['username'] ?? ''),
            'roleLabel' => (string) ($actor['roleLabel'] ?? ''),
        ];
    }

    /* ------------------------- Secure emailed link -------------------------- */

    public static function linkIsValid(string $token): bool
    {
        return self::linkRequest($token) !== null;
    }

    /**
     * Resolve a secure reset link without requiring the browser session that
     * requested it. Only the SHA-256 hash of the token is stored.
     */
    public static function linkRequest(string $token): ?array
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return null;
        }

        $request = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('password_reset_tokens') . '
             WHERE reset_token_hash = ?
               AND channel = "email"
               AND consumed_at IS NULL
               AND reset_completed_at IS NULL
               AND token_expires_at > NOW()
             LIMIT 1',
            [hash('sha256', $token)],
        );
        if (!$request) {
            return null;
        }

        $account = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [(int) $request['user_id']],
        );
        if (!$account) {
            return null;
        }
        if (
            Users::sessionType($account) === 'employee'
            && (($account['status'] ?? '') !== 'active' || !empty($account['deleted_at']))
        ) {
            return null;
        }

        $request['account'] = $account;
        return $request;
    }

    /**
     * Complete a reset from the emailed link. The password is hashed, every
     * session for the account is revoked, and the link is consumed once.
     *
     * @return array{ok: bool, error?: string, username?: string, roleLabel?: string}
     */
    public static function completeLinkReset(
        string $token,
        string $newPassword,
        string $confirmPassword,
    ): array {
        $request = self::linkRequest($token);
        if (!$request) {
            return ['ok' => false, 'error' => 'That reset link is invalid or has expired. Request a new link.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['ok' => false, 'error' => 'The new password confirmation does not match.'];
        }

        try {
            $newPassword = Validator::password($newPassword, 'New password');
        } catch (HttpException $error) {
            return ['ok' => false, 'error' => $error->getMessage()];
        }

        $account = $request['account'];
        if (password_verify($newPassword, (string) $account['password_hash'])) {
            return ['ok' => false, 'error' => 'Choose a password that is different from your current password.'];
        }

        $id = (int) $account['id'];
        $sessionType = Users::sessionType($account);

        Database::transaction(static function () use ($id, $newPassword, $request): void {
            Database::execute(
                'UPDATE ' . Schema::t('users') . '
                 SET password_hash = ?, must_change_password = 0, temp_password_expires_at = NULL,
                     password_changed_at = NOW(), failed_login_count = 0, locked_until = NULL
                 WHERE id = ?',
                [password_hash($newPassword, PASSWORD_DEFAULT), $id],
            );

            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . '
                 SET otp_hash = NULL, reset_token_hash = NULL,
                     consumed_at = NOW(), reset_completed_at = NOW()
                 WHERE id = ?',
                [(int) $request['id']],
            );
        });

        Auth::invalidateSessions($sessionType, $id);

        $actor = Users::mapUser($account);
        Auth::audit(
            $actor,
            'Password Reset',
            $sessionType === 'admin'
                ? 'The Administrator password was reset from a secure emailed link.'
                : 'A staff password was reset from a secure emailed link.',
            (string) ($actor['employeeId'] ?? 'ADMIN-01'),
        );

        unset(
            $_SESSION['auth_pwd_reset_request_id'],
            $_SESSION['auth_pwd_reset_email'],
            $_SESSION['auth_pwd_reset_token'],
            $_SESSION['auth_otp_last_sent'],
        );

        return [
            'ok' => true,
            'username' => (string) ($account['username'] ?? ''),
            'roleLabel' => (string) ($actor['roleLabel'] ?? ''),
        ];
    }

    public static function resetLink(string $token): string
    {
        return self::appUrl() . '/login/reset-password.php?token=' . rawurlencode($token);
    }

    private static function appUrl(): string
    {
        $configFile = __DIR__ . '/../config/config.php';
        $all = require $configFile;
        return rtrim((string) ($all['app']['url'] ?? 'http://localhost/guinhawa_system'), '/');
    }

    private static function findRecoverableAccount(string $email): ?array
    {
        $admin = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . '
             WHERE role = "admin" AND LOWER(email) = ? AND email_verified = 1 AND id = 1 LIMIT 1',
            [$email],
        );
        if ($admin) {
            return [
                'type' => 'admin',
                'id' => (int) $admin['id'],
                'code' => (string) ($admin['employee_code'] ?? 'ADMIN-01'),
                'roleLabel' => 'Administrator',
                'actor' => Users::mapAdmin($admin),
            ];
        }

        // Email ownership must be verified in the profile before recovery.
        // Inactive and archived accounts cannot reset their passwords.
        $employee = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . '
             WHERE LOWER(email) = ?
               AND email_verified = 1
               AND status = "active" AND deleted_at IS NULL
               AND role IN ("inventory-staff", "cashier", "counter-staff", "staff")
             LIMIT 1',
            [$email],
        );
        if ($employee) {
            return [
                'type' => 'employee',
                'id' => (int) $employee['id'],
                'code' => (string) $employee['employee_code'],
                'roleLabel' => Users::ROLE_LABELS[(string) $employee['role']] ?? 'Staff',
                'actor' => Users::mapEmployee($employee),
            ];
        }

        return null;
    }

    private static function ipCooldownRemaining(string $ip): int
    {
        if ($ip === '') {
            return 0;
        }

        $row = Database::fetchOne(
            'SELECT created_at FROM ' . Schema::t('password_reset_tokens') . '
             WHERE requested_ip = ? AND channel = "email"
             ORDER BY id DESC LIMIT 1',
            [substr($ip, 0, 45)],
        );
        if (!$row) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN - (time() - (int) strtotime((string) $row['created_at'])));
    }

    private static function tooManyRequests(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $row = Database::fetchOne(
            'SELECT COUNT(*) AS total FROM ' . Schema::t('password_reset_tokens') . '
             WHERE requested_ip = ? AND channel = "email"
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [substr($ip, 0, 45), self::REQUEST_WINDOW_SECONDS],
        );

        return (int) ($row['total'] ?? 0) >= self::MAX_REQUESTS_PER_WINDOW;
    }

    private static function otpEmailHtml(string $otp, string $roleLabel, string $link): string
    {
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $role = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:540px;margin:0 auto;color:#10271b;background:#f4f7f4;padding:24px">'
            . '<div style="background:#003d26;color:#ffffff;padding:24px;border-radius:16px 16px 0 0">'
            . '<p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#c5e8d4">Guinhawa Agri-Poultry Supply</p>'
            . '<h1 style="margin:8px 0 0;font-size:22px">Reset your password</h1></div>'
            . '<div style="background:#ffffff;border:1px solid #e2ebe4;border-top:0;padding:28px;border-radius:0 0 16px 16px">'
            . '<p style="margin-top:0">A password reset was requested for your ' . $role . ' account.</p>'
            . '<p style="margin:24px 0;text-align:center">'
            . '<a href="' . $safeLink . '" style="display:inline-block;background:#166534;color:#ffffff;text-decoration:none;font-weight:700;padding:14px 24px;border-radius:10px">'
            . 'Reset my password</a></p>'
            . '<p>This secure link expires in <strong>1 hour</strong> and works only once.</p>'
            . '<p>If the button does not open, copy this address into your browser:</p>'
            . '<p style="word-break:break-all;font-size:13px;color:#166534">' . $safeLink . '</p>'
            . '<p style="margin-top:24px">Prefer a code? Enter <strong style="font-size:22px;letter-spacing:4px;color:#168b4d">'
            . $safeOtp . '</strong> on the Forgot Password page. The code expires in 5 minutes and allows 5 attempts.</p>'
            . '<p style="color:#61796b;font-size:13px">If you did not request this, no action is needed and you can ignore this email.</p>'
            . '<p style="color:#8aa294;font-size:12px">Guinhawa Agri-Poultry Supply will never ask you to share this link or code.</p>'
            . '</div></div>';
    }

    private static function otpEmailText(string $otp, string $roleLabel, string $link): string
    {
        return "Guinhawa Agri-Poultry Supply\n\n"
            . "Password reset for your {$roleLabel} account\n\n"
            . "Open this secure link to choose a new password (expires in 1 hour, works once):\n{$link}\n\n"
            . "Or enter this verification code on the Forgot Password page: {$otp}\n"
            . "The code expires in 5 minutes and allows 5 attempts.\n"
            . "If you did not request this, no action is needed.\n";
    }
}
