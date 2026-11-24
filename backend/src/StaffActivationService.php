<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Email-based staff account activation.
 *
 * Admin creates the account -> a 32-byte single-use token is stored as a
 * SHA-256 hash in `email_verification_tokens` -> the staff member receives a
 * 24-hour link -> they choose their own password -> the account becomes Active
 * (or Inactive when the Admin chose an inactive account).
 *
 * The raw token only ever exists in the email and in the activation URL. It is
 * never stored in the database, returned by the API or written to a log.
 */
final class StaffActivationService
{
    public const TOKEN_TTL_SECONDS = 86400; // 24 hours
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Issue a fresh activation link for a pending account.
     *
     * Any earlier unused link is retired so only the newest email works.
     *
     * @return array{sent: bool, expiresAt: string, email: string, error: string}
     */
    public static function issue(int $employeeId, string $targetStatus, array $actor, string $ip = ''): array
    {
        $employee = self::loadEmployee($employeeId);
        if (($employee['status'] ?? '') !== 'pending') {
            throw new HttpException('That account is not waiting for activation.', 409, 'NOT_PENDING');
        }

        $email = trim((string) ($employee['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(
                'Add a valid work email address before sending the activation link.',
                422,
                'VALIDATION',
            );
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);
        $targetStatus = $targetStatus === 'inactive' ? 'inactive' : 'active';

        Database::transaction(static function () use ($employeeId, $hash, $targetStatus, $expiresAt, $ip): void {
            Database::execute(
                'UPDATE ' . Schema::t('email_verification_tokens') . '
                 SET used_at = NOW()
                 WHERE user_id = ? AND token_type = "activation" AND used_at IS NULL',
                [$employeeId],
            );

            Database::execute(
                'INSERT INTO ' . Schema::t('email_verification_tokens') . '
                    (user_id, token_type, pending_email, token_hash, expires_at, target_status,
                     last_sent_at, requested_ip)
                 VALUES (?, "activation", ?, ?, ?, ?, NOW(), ?)',
                [
                    $employeeId,
                    self::pendingEmailMarker($employeeId, $hash),
                    $hash,
                    $expiresAt,
                    $targetStatus,
                    substr($ip, 0, 45),
                ],
            );
        });

        $link = self::activationUrl($token);
        $sent = false;
        $error = '';

        try {
            Mailer::send(
                $email,
                'Activate your Guinhawa staff account',
                self::activationEmailHtml($employee, $link, $expiresAt),
                self::activationEmailText($employee, $link, $expiresAt),
            );
            $sent = true;
        } catch (\Throwable $mailError) {
            $error = $mailError->getMessage();
            error_log('[guinhawa] Staff activation email could not be sent: ' . $error);
        }

        Auth::audit(
            $actor,
            $sent ? 'Activation Link Sent' : 'Activation Link Not Sent',
            "{$employee['full_name']} ({$employee['employee_code']}) " .
                ($sent
                    ? 'was emailed a single-use account activation link valid for 24 hours.'
                    : 'activation email failed: ' . $error),
            (string) $employee['employee_code'],
        );

        return [
            'sent' => $sent,
            'expiresAt' => $expiresAt,
            'email' => $email,
            'error' => $sent ? '' : 'The activation email could not be sent. Check the Gmail SMTP settings and resend.',
        ];
    }

    /**
     * Email a newly created staff member that the account is ready to use.
     *
     * The account is already active, so this is informational only. It carries
     * the Employee ID, username and the login link, and it explains that the
     * same address is used whenever the staff member forgets their password.
     * No activation token is created or consumed.
     *
     * @return array{sent: bool, email: string, error: string}
     */
    public static function sendAccountReady(int $employeeId, array $actor): array
    {
        $employee = self::loadEmployee($employeeId);

        $email = trim((string) ($employee['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent' => false,
                'email' => $email,
                'error' => 'The account was created, but it has no valid work email address.',
            ];
        }

        $loginUrl = self::loginPageUrl();
        $sent = false;
        $error = '';

        try {
            Mailer::send(
                $email,
                'Your Guinhawa staff account is ready',
                self::accountReadyEmailHtml($employee, $loginUrl),
                self::accountReadyEmailText($employee, $loginUrl),
            );
            $sent = true;
        } catch (\Throwable $mailError) {
            $error = $mailError->getMessage();
            error_log('[guinhawa] Staff account-ready email could not be sent: ' . $error);
        }

        Auth::audit(
            $actor,
            $sent ? 'Account Ready Email Sent' : 'Account Ready Email Not Sent',
            "{$employee['full_name']} ({$employee['employee_code']}) " .
                ($sent
                    ? 'was emailed account-ready instructions with the login link.'
                    : 'account-ready email failed: ' . $error),
            (string) $employee['employee_code'],
        );

        return [
            'sent' => $sent,
            'email' => $email,
            'error' => $sent
                ? ''
                : 'The account was created, but the notification email could not be sent. Configure Gmail in Admin Settings > Email delivery.',
        ];
    }

    /**
     * Describe a token for the activation page.
     *
     * @return array{state: string, employee: ?array, expiresAt: ?string}
     */
    public static function describe(string $token): array
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['state' => 'invalid', 'employee' => null, 'expiresAt' => null];
        }

        $row = Database::fetchOne(
            'SELECT t.*, u.full_name, u.username, u.email, u.employee_code, u.role, u.status
             FROM ' . Schema::t('email_verification_tokens') . ' t
             JOIN ' . Schema::t('users') . ' u ON u.id = t.user_id
             WHERE t.token_type = "activation" AND t.token_hash = ? LIMIT 1',
            [hash('sha256', $token)],
        );

        if (!$row) {
            return ['state' => 'invalid', 'employee' => null, 'expiresAt' => null];
        }

        $expiresAt = (string) $row['expires_at'];
        $employee = [
            'id' => (int) $row['user_id'],
            'fullName' => $row['full_name'],
            'username' => $row['username'],
            'email' => $row['email'],
            'employeeId' => $row['employee_code'],
            'role' => $row['role'],
            'status' => $row['status'],
        ];

        if (!empty($row['used_at'])) {
            return ['state' => 'used', 'employee' => $employee, 'expiresAt' => $expiresAt];
        }
        if (strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            return ['state' => 'expired', 'employee' => $employee, 'expiresAt' => $expiresAt];
        }
        if (($row['status'] ?? '') !== 'pending') {
            return ['state' => 'used', 'employee' => $employee, 'expiresAt' => $expiresAt];
        }

        return ['state' => 'valid', 'employee' => $employee, 'expiresAt' => $expiresAt];
    }

    /**
     * Complete activation: set the staff member's own password and flip the
     * account to its intended status. Both writes happen in one transaction.
     *
     * @return array{ok: bool, error?: string, username?: string, roleLabel?: string, status?: string}
     */
    public static function complete(
        string $token,
        string $newPassword,
        string $confirmPassword,
        string $ip = '',
    ): array {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['ok' => false, 'error' => 'That activation link is invalid. Ask the Administrator for a new one.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['ok' => false, 'error' => 'The two passwords do not match.'];
        }

        try {
            $newPassword = Validator::password($newPassword, 'New password');
        } catch (HttpException $error) {
            return ['ok' => false, 'error' => $error->getMessage()];
        }

        $hash = hash('sha256', $token);
        $row = Database::fetchOne(
            'SELECT t.*, u.full_name, u.username, u.employee_code
             FROM ' . Schema::t('email_verification_tokens') . ' t
             JOIN ' . Schema::t('users') . ' u ON u.id = t.user_id
             WHERE t.token_type = "activation" AND t.token_hash = ? LIMIT 1',
            [$hash],
        );

        if (!$row) {
            return ['ok' => false, 'error' => 'That activation link is invalid. Ask the Administrator for a new one.'];
        }
        if (!empty($row['used_at'])) {
            return ['ok' => false, 'error' => 'That activation link has already been used. Sign in with your password.'];
        }
        if (strtotime((string) $row['expires_at']) !== false && strtotime((string) $row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'That activation link expired. Ask the Administrator to resend it.'];
        }

        $userId = (int) $row['user_id'];
        $employee = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [$userId],
        );
        if (!$employee) {
            return ['ok' => false, 'error' => 'That staff account no longer exists.'];
        }
        if (($employee['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'error' => 'This account has already been activated. Sign in instead.'];
        }

        $targetStatus = ($row['target_status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $updated = false;

        Database::transaction(static function () use ($employee, $row, $hash, $newPassword, $targetStatus, $ip, &$updated): void {
            // Lock the token row so two simultaneous submissions cannot both win.
            $locked = Database::fetchOne(
                'SELECT id FROM ' . Schema::t('email_verification_tokens') . '
                 WHERE token_type = "activation" AND token_hash = ? FOR UPDATE',
                [$hash],
            );
            if (!$locked) {
                return;
            }

            $changed = Database::execute(
                'UPDATE ' . Schema::t('users') . '
                 SET password_hash = ?, status = ?, email_verified = 1, email_verified_at = NOW(),
                     must_change_password = 0, temp_password_expires_at = NULL, password_changed_at = NOW(),
                     failed_login_count = 0, locked_until = NULL
                 WHERE id = ? AND status = "pending"',
                [password_hash($newPassword, PASSWORD_DEFAULT), $targetStatus, (int) $employee['id']],
            );
            if ($changed !== 1) {
                return;
            }

            $used = Database::execute(
                'UPDATE ' . Schema::t('email_verification_tokens') . '
                 SET used_at = NOW()
                 WHERE id = ? AND used_at IS NULL',
                [(int) $row['id']],
            );
            if ($used !== 1) {
                throw new HttpException('That activation link has already been used.', 409, 'TOKEN_USED');
            }

            Database::execute(
                'UPDATE ' . Schema::t('email_verification_tokens') . '
                 SET used_at = NOW()
                 WHERE user_id = ? AND token_type = "activation" AND used_at IS NULL',
                [(int) $employee['id']],
            );

            $updated = true;
        });

        if (!$updated) {
            return ['ok' => false, 'error' => 'This account has already been activated. Sign in instead.'];
        }

        Auth::invalidateSessions('employee', (int) $employee['id']);
        Auth::audit(
            ['type' => 'employee', 'id' => (int) $employee['id'], 'name' => $employee['full_name']],
            'Staff Account Activated',
            "{$employee['full_name']} ({$employee['employee_code']}) set their own password and activated the account from " . substr($ip, 0, 45) . '.',
            (string) $employee['employee_code'],
        );

        return [
            'ok' => true,
            'username' => (string) $employee['username'],
            'roleLabel' => Users::ROLE_LABELS[(string) $employee['role']] ?? 'Staff',
            'status' => $targetStatus,
        ];
    }

    /** Admin action: send a replacement link for a pending account. */
    public static function resend(int $employeeId, array $actor, string $ip = ''): array
    {
        $employee = self::loadEmployee($employeeId);

        $last = Database::fetchOne(
            'SELECT created_at, target_status FROM ' . Schema::t('email_verification_tokens') . '
             WHERE user_id = ? AND token_type = "activation" ORDER BY id DESC LIMIT 1',
            [$employeeId],
        );
        if ($last) {
            $wait = self::RESEND_COOLDOWN_SECONDS - (time() - (int) strtotime((string) $last['created_at']));
            if ($wait > 0) {
                throw new HttpException(
                    'Please wait ' . $wait . ' seconds before sending another activation link.',
                    429,
                    'RATE_LIMITED',
                );
            }
        }

        $target = (string) ($last['target_status'] ?? 'active');
        return self::issue($employeeId, $target, $actor, $ip);
    }

    /** Admin action: cancel a pending invitation and deactivate the account. */
    public static function cancel(int $employeeId, array $actor): array
    {
        $employee = self::loadEmployee($employeeId);

        Database::transaction(static function () use ($employeeId): void {
            Database::execute(
                'UPDATE ' . Schema::t('email_verification_tokens') . '
                 SET used_at = NOW() WHERE user_id = ? AND token_type = "activation" AND used_at IS NULL',
                [$employeeId],
            );
            Database::execute(
                'UPDATE ' . Schema::t('users') . ' SET status = "inactive"
                 WHERE id = ? AND status = "pending"',
                [$employeeId],
            );
        });

        Auth::invalidateSessions('employee', $employeeId);
        Auth::audit(
            $actor,
            'Pending Activation Cancelled',
            "{$employee['full_name']} ({$employee['employee_code']}) pending invitation was cancelled and the account deactivated.",
            (string) $employee['employee_code'],
        );

        return ['ok' => true];
    }

    /** Pending accounts for the Admin dashboard, newest first. */
    public static function pendingAccounts(): array
    {
        $rows = Database::fetchAll(
            "SELECT u.id, u.employee_code, u.username, u.full_name, u.email, u.role,
                    u.date_joined, u.created_at, t.expires_at, t.created_at AS sent_at
             FROM " . Schema::t('users') . " u
             LEFT JOIN " . Schema::t('email_verification_tokens') . " t ON t.id = (
                 SELECT t2.id FROM " . Schema::t('email_verification_tokens') . " t2
                 WHERE t2.user_id = u.id AND t2.token_type = 'activation'
                 ORDER BY t2.id DESC LIMIT 1
             )
             WHERE u.status = 'pending' AND u.deleted_at IS NULL
               AND u.role IN ('inventory-staff', 'cashier', 'counter-staff', 'staff')
             ORDER BY u.created_at DESC, u.id DESC",
        );

        return array_map(static function (array $row): array {
            $mapped = Users::mapEmployee($row + ['status' => 'pending']);
            $mapped['workShift'] = Users::STANDARD_WORK_SCHEDULE;
            $mapped['dateJoined'] = $row['date_joined'] ?? null;
            $mapped['activationExpiresAt'] = $row['expires_at'] ?? null;
            $mapped['activationSentAt'] = $row['sent_at'] ?? null;

            return $mapped;
        }, $rows);
    }

    public static function activationUrl(string $token): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $base = rtrim((string) ($config['app']['url'] ?? ''), '/');
        if ($base === '') {
            $base = rtrim((string) ($config['app_base_path'] ?? '/guinhawa_system'), '/');
        }

        return $base . '/login/activate.php?token=' . rawurlencode($token);
    }

    /** Public sign-in page used in account-ready emails. */
    public static function loginPageUrl(): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $base = rtrim((string) ($config['app']['url'] ?? ''), '/');
        if ($base === '') {
            $base = rtrim((string) ($config['app_base_path'] ?? '/guinhawa_system'), '/');
        }

        return $base . '/login/index.php';
    }

    private static function loadEmployee(int $employeeId): array
    {
        $employee = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . ' WHERE id = ? LIMIT 1',
            [$employeeId],
        );
        if (!$employee) {
            throw new HttpException('That account no longer exists.', 404, 'NOT_FOUND');
        }
        if (!empty($employee['deleted_at'])) {
            throw new HttpException('That staff account was archived. Restore it first.', 409, 'ARCHIVED');
        }
        if (Users::sessionType($employee) !== 'employee') {
            throw new HttpException('Administrator accounts are not managed here.', 400, 'VALIDATION');
        }

        return $employee;
    }

    /** Unique marker avoids the globally unique pending_email column. */
    private static function pendingEmailMarker(int $userId, string $hash): string
    {
        return 'activation:' . $userId . ':' . $hash;
    }

    private static function activationEmailHtml(array $employee, string $link, string $expiresAt): string
    {
        $name = htmlspecialchars((string) $employee['full_name'], ENT_QUOTES, 'UTF-8');
        $role = Users::ROLE_LABELS[(string) $employee['role']] ?? 'Staff';
        $roleSafe = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string) $employee['employee_code'], ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars((string) $employee['username'], ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $expiry = date('M j, Y g:i A', strtotime($expiresAt));

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#10271b;background:#f4f7f4;padding:24px">'
            . '<div style="background:#003d26;color:#ffffff;padding:24px;border-radius:16px 16px 0 0">'
            . '<p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#c5e8d4">Guinhawa Agri-Poultry Supply</p>'
            . '<h1 style="margin:8px 0 0;font-size:22px">Activate your staff account</h1></div>'
            . '<div style="background:#ffffff;border:1px solid #e2ebe4;border-top:0;padding:28px;border-radius:0 0 16px 16px">'
            . '<p style="margin-top:0">Hello <strong>' . $name . '</strong>,</p>'
            . '<p>The Administrator created a Guinhawa account for you. Choose your own password to activate it.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:18px 0;font-size:14px">'
            . '<tr><td style="padding:6px 0;color:#61796b">Employee ID</td><td style="padding:6px 0;text-align:right;font-weight:700">' . $code . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#61796b">Username</td><td style="padding:6px 0;text-align:right;font-weight:700">@' . $username . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#61796b">Role</td><td style="padding:6px 0;text-align:right;font-weight:700">' . $roleSafe . '</td></tr>'
            . '</table>'
            . '<p style="text-align:center;margin:26px 0">'
            . '<a href="' . $url . '" style="display:inline-block;background:#168b4d;color:#ffffff;text-decoration:none;'
            . 'font-weight:700;padding:14px 28px;border-radius:10px">Create my password</a></p>'
            . '<p style="font-size:13px;color:#61796b">This link works once and expires in <strong>24 hours</strong> ('
            . htmlspecialchars($expiry) . '). If it stops working, ask the Administrator to resend it.</p>'
            . '<p style="font-size:12px;color:#8aa294;word-break:break-all">If the button does not work, copy this address into your browser:<br>'
            . $url . '</p>'
            . '<p style="font-size:12px;color:#8aa294">If you were not expecting this email, you can ignore it.</p>'
            . '</div></div>';
    }

    private static function activationEmailText(array $employee, string $link, string $expiresAt): string
    {
        $expiry = date('M j, Y g:i A', strtotime($expiresAt));

        return "Guinhawa Agri-Poultry Supply\n\n"
            . "Activate your staff account\n\n"
            . "Hello {$employee['full_name']},\n\n"
            . "The Administrator created a Guinhawa account for you.\n"
            . "Employee ID: {$employee['employee_code']}\n"
            . "Username: {$employee['username']}\n\n"
            . "Create your password here:\n{$link}\n\n"
            . "This link works once and expires in 24 hours ({$expiry}).\n"
            . "If it stops working, ask the Administrator to resend it.\n";
    }

    private static function accountReadyEmailHtml(array $employee, string $loginUrl): string
    {
        $name = htmlspecialchars((string) $employee['full_name'], ENT_QUOTES, 'UTF-8');
        $role = Users::ROLE_LABELS[(string) $employee['role']] ?? 'Staff';
        $roleSafe = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string) $employee['employee_code'], ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars((string) $employee['username'], ENT_QUOTES, 'UTF-8');
        $schedule = htmlspecialchars(Users::STANDARD_WORK_SCHEDULE, ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#10271b;background:#f4f7f4;padding:24px">'
            . '<div style="background:#003d26;color:#ffffff;padding:24px;border-radius:16px 16px 0 0">'
            . '<p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#c5e8d4">Guinhawa Agri-Poultry Supply</p>'
            . '<h1 style="margin:8px 0 0;font-size:22px">Your staff account is ready</h1></div>'
            . '<div style="background:#ffffff;border:1px solid #e2ebe4;border-top:0;padding:28px;border-radius:0 0 16px 16px">'
            . '<p style="margin-top:0">Hello <strong>' . $name . '</strong>,</p>'
            . '<p>The Administrator created your Guinhawa staff account. To sign in for the first time, set your password with the <strong>Forgot password</strong> option on the login page.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:18px 0;font-size:14px">'
            . '<tr><td style="padding:6px 0;color:#61796b">Employee ID</td><td style="padding:6px 0;text-align:right;font-weight:700">' . $code . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#61796b">Username</td><td style="padding:6px 0;text-align:right;font-weight:700">@' . $username . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#61796b">Role</td><td style="padding:6px 0;text-align:right;font-weight:700">' . $roleSafe . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#61796b">Work schedule</td><td style="padding:6px 0;text-align:right;font-weight:700">' . $schedule . '</td></tr>'
            . '</table>'
            . '<p style="text-align:center;margin:26px 0">'
            . '<a href="' . $url . '" style="display:inline-block;background:#168b4d;color:#ffffff;text-decoration:none;'
            . 'font-weight:700;padding:14px 28px;border-radius:10px">Open the Guinhawa login page</a></p>'
            . '<p style="margin-bottom:6px"><strong>How to sign in</strong></p>'
            . '<ol style="margin-top:0;padding-left:20px;font-size:14px;color:#40584a">'
            . '<li>Open the login page with the button above.</li>'
            . '<li>Choose <strong>Forgot password</strong>.</li>'
            . '<li>Enter this same email address and follow the code we send you to create your password.</li>'
            . '<li>Return to the login page and sign in with your username <strong>@' . $username . '</strong> and your new password.</li>'
            . '</ol>'
            . '<p style="font-size:13px;color:#61796b">This email address is also used whenever you forget your password. '
            . 'Keep access to this mailbox so you can always recover your account.</p>'
            . '<p style="font-size:12px;color:#8aa294;word-break:break-all">If the button does not work, copy this address into your browser:<br>'
            . $url . '</p>'
            . '<p style="font-size:12px;color:#8aa294">If you were not expecting this email, please tell the Administrator.</p>'
            . '</div></div>';
    }

    private static function accountReadyEmailText(array $employee, string $loginUrl): string
    {
        return "Guinhawa Agri-Poultry Supply\n\n"
            . "Your staff account is ready\n\n"
            . "Hello {$employee['full_name']},\n\n"
            . "The Administrator created your Guinhawa staff account. To sign in for the first time, set your password with the Forgot password option on the login page.\n\n"
            . "Employee ID: {$employee['employee_code']}\n"
            . "Username: {$employee['username']}\n"
            . "Work schedule: " . Users::STANDARD_WORK_SCHEDULE . "\n\n"
            . "Login page:\n{$loginUrl}\n\n"
            . "How to sign in:\n"
            . "1. Open the login page above.\n"
            . "2. Choose Forgot password.\n"
            . "3. Enter this same email address and follow the code we send you to create your password.\n"
            . "4. Return to the login page and sign in with your username and your new password.\n\n"
            . "This email address is also used whenever you forget your password. "
            . "Keep access to this mailbox so you can always recover your account.\n";
    }
}
