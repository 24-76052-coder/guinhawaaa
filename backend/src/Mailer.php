<?php

declare(strict_types=1);

namespace Guinhawa;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Transactional email for account activation and password-reset codes.
 *
 * Drivers:
 *   smtp  PHPMailer over SMTP (Gmail: smtp.gmail.com:587 + STARTTLS)
 *   mail  PHPMailer using PHP mail() for hosts with a local sendmail
 *   file  local test outbox written outside htdocs; never used in production
 *
 * SMTP credentials are read from the protected config file (or environment)
 * and are never written to HTML, JavaScript or logs. Message bodies are never
 * logged.
 */
final class Mailer
{
    private const VENDOR_DIR = __DIR__ . '/../vendor/PHPMailer/src/';

    public static function send(string $to, string $subject, string $html, string $text): void
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('The recipient email address is invalid.');
        }

        $config = self::config();
        $driver = strtolower((string) ($config['driver'] ?? 'smtp'));

        if ($driver === 'file') {
            self::writeSpool($to, $subject, $html, $text, $config);
            return;
        }

        self::loadPHPMailer();

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout = max(5, (int) ($config['timeout'] ?? 15));
        $mail->SMTPTimeout = $mail->Timeout;

        if ($driver === 'mail') {
            $mail->isMail();
        } else {
            self::configureSmtp($mail, $config);
        }

        $fromEmail = self::fromAddress($config);
        $fromName = self::safeHeader((string) ($config['from_name'] ?? 'Guinhawa Agri-Poultry Supply'));

        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;

        try {
            $mail->send();
        } catch (\Throwable $error) {
            // PHPMailer's ErrorInfo has the useful server response; the SMTP
            // password is never included in it.
            $detail = trim((string) $mail->ErrorInfo);
            throw new \RuntimeException(
                'The email could not be sent' . ($detail !== '' ? ': ' . $detail : '.'),
                0,
                $error,
            );
        }
    }

    public static function driver(): string
    {
        return strtolower((string) (self::config()['driver'] ?? 'smtp'));
    }

    /** Whether the selected mail driver has the minimum settings needed to send. */
    public static function isConfigured(): bool
    {
        $config = self::config();
        $driver = strtolower((string) ($config['driver'] ?? 'smtp'));

        if ($driver === 'file') {
            return trim((string) ($config['spool_dir'] ?? '')) !== '';
        }
        if ($driver === 'mail') {
            return function_exists('mail');
        }

        return trim((string) ($config['username'] ?? '')) !== ''
            && trim((string) ($config['password'] ?? '')) !== '';
    }

    private static function configureSmtp(PHPMailer $mail, array $config): void
    {
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new \RuntimeException(
                'Gmail SMTP is not configured. Add the dedicated Guinhawa address and app password ' .
                'to the protected config.local.php file outside the web root.',
            );
        }

        $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));

        $mail->isSMTP();
        $mail->Host = (string) ($config['host'] ?? 'smtp.gmail.com');
        $mail->Port = (int) ($config['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $encryption === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = true;
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
    }

    private static function fromAddress(array $config): string
    {
        $configured = trim((string) ($config['from_email'] ?? ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $username = trim((string) ($config['username'] ?? ''));
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return $username;
        }

        return 'no-reply@guinhawa.local';
    }

    private static function loadPHPMailer(): void
    {
        if (class_exists(PHPMailer::class, false)) {
            return;
        }

        foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $file) {
            $path = self::VENDOR_DIR . $file;
            if (!is_file($path)) {
                throw new \RuntimeException('PHPMailer is not installed. Run backend/scripts/install_phpmailer.php.');
            }
            require_once $path;
        }
    }

    private static function config(): array
    {
        $configFile = __DIR__ . '/../config/config.php';
        $all = require $configFile;
        return is_array($all['mail'] ?? null) ? $all['mail'] : [];
    }

    private static function writeSpool(string $to, string $subject, string $html, string $text, array $config): void
    {
        $directory = rtrim((string) ($config['spool_dir'] ?? 'C:/xampp/guinhawa-mail'), '/\\');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('The local mail spool directory could not be created.');
        }

        $filename = $directory . DIRECTORY_SEPARATOR . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.eml';
        $message = self::buildMessage($to, $subject, $html, $text, $config);
        if (file_put_contents($filename, $message, LOCK_EX) === false) {
            throw new \RuntimeException('The local mail spool file could not be written.');
        }
    }

    private static function buildMessage(string $to, string $subject, string $html, string $text, array $config): string
    {
        $fromEmail = self::safeHeader(self::fromAddress($config));
        $fromName = self::safeHeader((string) ($config['from_name'] ?? 'Guinhawa Agri-Poultry Supply'));
        $boundary = 'gq-' . bin2hex(random_bytes(12));

        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@guinhawa.local>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: Guinhawa-Auth',
        ];

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $text . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n\r\n"
            . '--' . $boundary . "--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private static function safeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], ' ', $value));
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode(self::safeHeader($value)) . '?=';
    }
}
