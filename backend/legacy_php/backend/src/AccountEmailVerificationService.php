<?php

declare(strict_types=1);

namespace Guinhawa;

use PDOException;

/** One email verification flow shared by Admin and Staff accounts. */
final class AccountEmailVerificationService
{
    private const CODE_TTL_MINUTES = 10;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    public static function status(array $user): array
    {
        [$type, $table, $id] = self::accountParts($user);
        $account = self::loadAccount($table, $id, $type);
        $pending = Database::fetchOne(
            'SELECT pending_email, expires_at, last_sent_at
             FROM account_email_verifications WHERE user_type = ? AND user_id = ? LIMIT 1',
            [$type, $id],
        );

        return [
            'email' => (string) ($account['email'] ?? ''),
            'verified' => (bool) ($account['email_verified'] ?? false),
            'verifiedAt' => $account['email_verified_at'] ?? null,
            'pendingEmail' => (string) ($pending['pending_email'] ?? ''),
            'expiresAt' => $pending['expires_at'] ?? null,
            'resendAvailableAt' => $pending
                ? date('Y-m-d H:i:s', strtotime((string) $pending['last_sent_at']) + self::RESEND_COOLDOWN_SECONDS)
                : null,
        ];
    }

    public static function requestCode(array $user, mixed $rawEmail): array
    {
        [$type, $table, $id] = self::accountParts($user);
        $email = Validator::email($rawEmail, true);
        $account = self::loadAccount($table, $id, $type);

        if (
            strcasecmp((string) ($account['email'] ?? ''), $email) === 0
            && !empty($account['email_verified'])
        ) {
            return ['alreadyVerified' => true, 'verification' => self::status($user)];
        }

        self::assertEmailAvailable($email, $type, $id);

        $pending = Database::fetchOne(
            'SELECT user_type, user_id FROM account_email_verifications
             WHERE pending_email = ? AND (user_type <> ? OR user_id <> ?) LIMIT 1',
            [$email, $type, $id],
        );
        if ($pending) {
            throw new HttpException('That email address is already being verified for another account.', 409, 'CONFLICT');
        }

        $existing = Database::fetchOne(
            'SELECT last_sent_at FROM account_email_verifications WHERE user_type = ? AND user_id = ? LIMIT 1',
            [$type, $id],
        );
        if ($existing) {
            $wait = self::RESEND_COOLDOWN_SECONDS - (time() - (int) strtotime((string) $existing['last_sent_at']));
            if ($wait > 0) {
                throw new HttpException('Wait ' . $wait . ' seconds before requesting another verification code.', 429, 'RATE_LIMITED');
            }
        }

        $code = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + self::CODE_TTL_MINUTES * 60);
        $codeHash = password_hash($code, PASSWORD_DEFAULT);

        try {
            Database::execute(
                'INSERT INTO account_email_verifications
                    (user_type, user_id, pending_email, otp_hash, expires_at, attempts, last_sent_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())
                 ON DUPLICATE KEY UPDATE
                    pending_email = VALUES(pending_email), otp_hash = VALUES(otp_hash),
                    expires_at = VALUES(expires_at), attempts = 0, last_sent_at = NOW()',
                [$type, $id, $email, $codeHash, $expiresAt],
            );
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                throw new HttpException('That email address is already being verified for another account.', 409, 'CONFLICT');
            }
            throw $error;
        }

        try {
            Mailer::send(
                $email,
                'Verify your Guinhawa personal email',
                self::emailHtml($code),
                self::emailText($code),
            );
        } catch (\Throwable $error) {
            Database::execute(
                'DELETE FROM account_email_verifications
                 WHERE user_type = ? AND user_id = ? AND otp_hash = ?',
                [$type, $id, $codeHash],
            );
            error_log('[guinhawa] Personal email verification message could not be sent: ' . $error->getMessage());
            $message = Mailer::isConfigured()
                ? 'We could not deliver the verification code. Please contact an administrator to check email delivery.'
                : 'Email verification is temporarily unavailable. Please contact an administrator to enable email delivery.';
            throw new HttpException($message, 503, 'MAIL_UNAVAILABLE');
        }

        Auth::audit(
            $user,
            'Personal Email Verification Requested',
            'A 6-digit verification code was sent to ' . $email . '.',
            (string) ($user['employeeId'] ?? 'ADMIN-01'),
        );

        return [
            'alreadyVerified' => false,
            'message' => 'A verification code was sent to ' . $email . '. It expires in 10 minutes.',
            'verification' => self::status($user),
        ];
    }

    public static function verifyCode(array $user, mixed $rawCode): array
    {
        [$type, $table, $id] = self::accountParts($user);
        $code = trim((string) ($rawCode ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            throw new HttpException('Enter the 6-digit code sent to your email.', 422, 'VALIDATION');
        }

        $pending = Database::fetchOne(
            'SELECT * FROM account_email_verifications WHERE user_type = ? AND user_id = ? LIMIT 1',
            [$type, $id],
        );
        if (!$pending) {
            throw new HttpException('Request a new email verification code first.', 422, 'VERIFICATION_MISSING');
        }
        if (strtotime((string) $pending['expires_at']) < time()) {
            Database::execute(
                'DELETE FROM account_email_verifications WHERE user_type = ? AND user_id = ?',
                [$type, $id],
            );
            throw new HttpException('That verification code expired. Request a new one.', 422, 'VERIFICATION_EXPIRED');
        }
        if ((int) $pending['attempts'] >= self::MAX_ATTEMPTS) {
            Database::execute(
                'DELETE FROM account_email_verifications WHERE user_type = ? AND user_id = ?',
                [$type, $id],
            );
            throw new HttpException('Too many incorrect attempts. Request a new verification code.', 429, 'RATE_LIMITED');
        }
        if (!password_verify($code, (string) $pending['otp_hash'])) {
            $attempts = (int) $pending['attempts'] + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                Database::execute(
                    'DELETE FROM account_email_verifications WHERE user_type = ? AND user_id = ?',
                    [$type, $id],
                );
                throw new HttpException('Too many incorrect attempts. Request a new verification code.', 429, 'RATE_LIMITED');
            }
            Database::execute(
                'UPDATE account_email_verifications SET attempts = ?
                 WHERE user_type = ? AND user_id = ?',
                [$attempts, $type, $id],
            );
            throw new HttpException('That code is incorrect. ' . (self::MAX_ATTEMPTS - $attempts) . ' attempt(s) remain.', 422, 'INVALID_CODE');
        }

        $email = (string) $pending['pending_email'];
        Database::transaction(static function () use ($type, $table, $id, $email, $pending): void {
            $locked = Database::fetchOne(
                'SELECT otp_hash, expires_at, attempts FROM account_email_verifications
                 WHERE user_type = ? AND user_id = ? FOR UPDATE',
                [$type, $id],
            );
            if (
                !$locked
                || !hash_equals((string) $pending['otp_hash'], (string) $locked['otp_hash'])
                || strtotime((string) $locked['expires_at']) < time()
                || (int) $locked['attempts'] >= self::MAX_ATTEMPTS
            ) {
                throw new HttpException('That verification code expired or was already used. Request a new one.', 422, 'VERIFICATION_EXPIRED');
            }

            self::assertEmailAvailable($email, $type, $id);
            $consumed = Database::execute(
                'DELETE FROM account_email_verifications
                 WHERE user_type = ? AND user_id = ? AND otp_hash = ?',
                [$type, $id, (string) $pending['otp_hash']],
            );
            if ($consumed !== 1) {
                throw new HttpException('That verification code was already used. Request a new one.', 422, 'VERIFICATION_EXPIRED');
            }

            Database::execute(
                "UPDATE {$table} SET email = ?, email_verified = 1, email_verified_at = NOW() WHERE id = ?",
                [$email, $id],
            );
            Database::execute(
                "UPDATE password_reset_requests SET consumed_at = NOW()
                 WHERE user_type = ? AND user_id = ? AND channel = 'email'
                   AND consumed_at IS NULL AND reset_completed_at IS NULL",
                [$type, $id],
            );
        });

        $fresh = self::loadAccount($table, $id, $type);
        $mapped = $type === 'admin' ? Users::mapAdmin($fresh) : Users::mapEmployee($fresh);
        Auth::audit(
            $mapped,
            'Personal Email Verified',
            $mapped['fullName'] . ' verified the personal email address ' . $email . '.',
            (string) ($mapped['employeeId'] ?? 'ADMIN-01'),
        );

        return ['user' => $mapped, 'verification' => self::status($mapped)];
    }

    private static function accountParts(array $user): array
    {
        $type = (string) ($user['type'] ?? '');
        if (!in_array($type, ['admin', 'employee'], true)) {
            throw new HttpException('Email verification is available for Admin and Staff accounts only.', 403, 'FORBIDDEN');
        }

        return [$type, $type === 'admin' ? 'admin' : 'employees', (int) $user['id']];
    }

    private static function loadAccount(string $table, int $id, string $type): array
    {
        $where = $type === 'employee' ? ' AND deleted_at IS NULL' : '';
        $account = Database::fetchOne("SELECT * FROM {$table} WHERE id = ?{$where} LIMIT 1", [$id]);
        if (!$account) {
            throw new HttpException('That account is unavailable.', 404, 'NOT_FOUND');
        }

        return $account;
    }

    private static function assertEmailAvailable(string $email, string $type, int $id): void
    {
        $admin = Database::fetchOne(
            'SELECT id FROM admin WHERE LOWER(email) = ?' . ($type === 'admin' ? ' AND id <> ?' : '') . ' LIMIT 1',
            $type === 'admin' ? [$email, $id] : [$email],
        );
        if ($admin) {
            throw new HttpException('That email address is already in use.', 409, 'CONFLICT');
        }

        $employee = Database::fetchOne(
            'SELECT id FROM employees WHERE LOWER(email) = ? AND id <> ? AND deleted_at IS NULL LIMIT 1',
            [$email, $type === 'employee' ? $id : 0],
        );
        if ($employee) {
            throw new HttpException('That email address is already in use.', 409, 'CONFLICT');
        }
    }

    private static function emailHtml(string $code): string
    {
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        return '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#10271b">'
            . '<h1 style="font-size:20px;margin:0 0 12px">Verify your personal email</h1>'
            . '<p>Enter this code in your Guinhawa profile to confirm that you own this address:</p>'
            . '<p style="font-size:28px;font-weight:bold;letter-spacing:8px">' . $safeCode . '</p>'
            . '<p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>'
            . '</div>';
    }

    private static function emailText(string $code): string
    {
        return "Verify your personal email\n\n"
            . "Enter this code in your Guinhawa profile: {$code}\n\n"
            . "This code expires in 10 minutes. If you did not request it, you can ignore this email.\n";
    }
}
