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
        $account = self::loadAccount((int) $user['id']);
        $pending = Database::fetchOne(
            'SELECT pending_email, expires_at, last_sent_at
             FROM ' . Schema::t('email_verification_tokens') . '
             WHERE user_id = ? AND token_type = "email_change" AND used_at IS NULL LIMIT 1',
            [(int) $user['id']],
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
        $id = (int) $user['id'];
        $email = Validator::email($rawEmail, true);
        $account = self::loadAccount($id);

        if (
            strcasecmp((string) ($account['email'] ?? ''), $email) === 0
            && !empty($account['email_verified'])
        ) {
            return ['alreadyVerified' => true, 'verification' => self::status($user)];
        }

        self::assertEmailAvailable($email, $id);

        $pending = Database::fetchOne(
            'SELECT id FROM ' . Schema::t('email_verification_tokens') . '
             WHERE pending_email = ? AND token_type = "email_change"
               AND user_id <> ? AND used_at IS NULL LIMIT 1',
            [$email, $id],
        );
        if ($pending) {
            throw new HttpException('That email address is already being verified for another account.', 409, 'CONFLICT');
        }

        $existing = Database::fetchOne(
            'SELECT last_sent_at FROM ' . Schema::t('email_verification_tokens') . '
             WHERE user_id = ? AND token_type = "email_change" AND used_at IS NULL LIMIT 1',
            [$id],
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

        Database::execute(
            'DELETE FROM ' . Schema::t('email_verification_tokens') . '
             WHERE user_id = ? AND token_type = "email_change" AND used_at IS NULL',
            [$id],
        );
        try {
            Database::execute(
                'INSERT INTO ' . Schema::t('email_verification_tokens') . '
                    (user_id, token_type, pending_email, token_hash, expires_at, attempts, max_attempts,
                     last_sent_at, requested_ip)
                 VALUES (?, "email_change", ?, ?, ?, 0, ?, NOW(), ?)',
                [$id, $email, $codeHash, $expiresAt, self::MAX_ATTEMPTS, self::emailIp()],
            );
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                throw new HttpException('That email address is already being verified for another account.', 409, 'CONFLICT');
            }
            throw $error;
        }
        $tokenId = Database::lastId();

        try {
            Mailer::send(
                $email,
                'Verify your Guinhawa personal email',
                self::emailHtml($code),
                self::emailText($code),
            );
        } catch (\Throwable $error) {
            Database::execute(
                'DELETE FROM ' . Schema::t('email_verification_tokens') . ' WHERE id = ?',
                [$tokenId],
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
        $id = (int) $user['id'];
        $code = trim((string) ($rawCode ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            throw new HttpException('Enter the 6-digit code sent to your email.', 422, 'VALIDATION');
        }

        $pending = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('email_verification_tokens') . '
             WHERE user_id = ? AND token_type = "email_change" AND used_at IS NULL LIMIT 1',
            [$id],
        );
        if (!$pending) {
            throw new HttpException('Request a new email verification code first.', 422, 'VERIFICATION_MISSING');
        }
        $maxAttempts = max(1, (int) $pending['max_attempts']);

        if (strtotime((string) $pending['expires_at']) < time()) {
            self::consume((int) $pending['id']);
            throw new HttpException('That verification code expired. Request a new one.', 422, 'VERIFICATION_EXPIRED');
        }
        if ((int) $pending['attempts'] >= $maxAttempts) {
            self::consume((int) $pending['id']);
            throw new HttpException('Too many incorrect attempts. Request a new verification code.', 429, 'RATE_LIMITED');
        }
        if (!password_verify($code, (string) $pending['token_hash'])) {
            $attempts = (int) $pending['attempts'] + 1;
            if ($attempts >= $maxAttempts) {
                self::consume((int) $pending['id']);
                throw new HttpException('Too many incorrect attempts. Request a new verification code.', 429, 'RATE_LIMITED');
            }
            Database::execute(
                'UPDATE ' . Schema::t('email_verification_tokens') . ' SET attempts = ? WHERE id = ?',
                [$attempts, (int) $pending['id']],
            );
            throw new HttpException('That code is incorrect. ' . ($maxAttempts - $attempts) . ' attempt(s) remain.', 422, 'INVALID_CODE');
        }

        $email = (string) $pending['pending_email'];
        Database::transaction(static function () use ($pending, $id, $email, $maxAttempts): void {
            $locked = Database::fetchOne(
                'SELECT * FROM ' . Schema::t('email_verification_tokens') . ' WHERE id = ? FOR UPDATE',
                [(int) $pending['id']],
            );
            if (
                !$locked
                || !empty($locked['used_at'])
                || !hash_equals((string) $pending['token_hash'], (string) $locked['token_hash'])
                || strtotime((string) $locked['expires_at']) < time()
                || (int) $locked['attempts'] >= $maxAttempts
            ) {
                throw new HttpException('That verification code expired or was already used. Request a new one.', 422, 'VERIFICATION_EXPIRED');
            }

            self::assertEmailAvailable($email, $id);
            $consumed = Database::execute(
                'UPDATE ' . Schema::t('email_verification_tokens') . '
                 SET used_at = NOW() WHERE id = ? AND used_at IS NULL',
                [(int) $pending['id']],
            );
            if ($consumed !== 1) {
                throw new HttpException('That verification code was already used. Request a new one.', 422, 'VERIFICATION_EXPIRED');
            }

            Database::execute(
                'UPDATE ' . Schema::t('users') . '
                 SET email = ?, email_verified = 1, email_verified_at = NOW() WHERE id = ?',
                [$email, $id],
            );
            Database::execute(
                'UPDATE ' . Schema::t('password_reset_tokens') . '
                 SET consumed_at = NOW()
                 WHERE user_id = ? AND channel = "email"
                   AND consumed_at IS NULL AND reset_completed_at IS NULL',
                [$id],
            );
        });

        $fresh = self::loadAccount($id);
        $mapped = Users::mapUser($fresh);
        Auth::audit(
            $mapped,
            'Personal Email Verified',
            $mapped['fullName'] . ' verified the personal email address ' . $email . '.',
            (string) ($mapped['employeeId'] ?? 'ADMIN-01'),
        );

        return ['user' => $mapped, 'verification' => self::status($mapped)];
    }

    private static function loadAccount(int $id): array
    {
        $account = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('users') . '
             WHERE id = ? AND (role = "admin" OR deleted_at IS NULL) LIMIT 1',
            [$id],
        );
        if (!$account) {
            throw new HttpException('That account is unavailable.', 404, 'NOT_FOUND');
        }

        return $account;
    }

    private static function assertEmailAvailable(string $email, int $id): void
    {
        $account = Database::fetchOne(
            'SELECT id FROM ' . Schema::t('users') . '
             WHERE LOWER(email) = ? AND id <> ?
               AND (role = "admin" OR deleted_at IS NULL) LIMIT 1',
            [$email, $id],
        );
        if ($account) {
            throw new HttpException('That email address is already in use.', 409, 'CONFLICT');
        }
    }

    private static function consume(int $id): void
    {
        Database::execute(
            'UPDATE ' . Schema::t('email_verification_tokens') . ' SET used_at = NOW()
             WHERE id = ? AND used_at IS NULL',
            [$id],
        );
    }

    private static function emailIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
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
