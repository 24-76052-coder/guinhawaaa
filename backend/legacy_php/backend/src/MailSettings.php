<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Read and write the protected Gmail SMTP settings from the Admin Settings
 * page. Credentials are written to config.local.php outside the web root and
 * are never returned to the browser.
 */
final class MailSettings
{
    private const DEFAULT_FROM_NAME = 'Guinhawa Agri-Poultry Supply';

    public static function configPath(): string
    {
        $path = getenv('GUINHAWA_CONFIG_FILE') ?: 'C:/xampp/guinhawa-config/config.local.php';

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Current mail settings without the password.
     *
     * @return array{
     *     driver: string, host: string, port: int, encryption: string,
     *     username: string, fromEmail: string, fromName: string,
     *     passwordSet: bool, configPath: string, configExists: bool
     * }
     */
    public static function current(): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
        $path = self::configPath();

        return [
            'driver' => (string) ($mail['driver'] ?? 'smtp'),
            'host' => (string) ($mail['host'] ?? 'smtp.gmail.com'),
            'port' => (int) ($mail['port'] ?? 587),
            'encryption' => (string) ($mail['encryption'] ?? 'tls'),
            'username' => (string) ($mail['username'] ?? ''),
            'fromEmail' => (string) ($mail['from_email'] ?? ''),
            'fromName' => (string) ($mail['from_name'] ?? self::DEFAULT_FROM_NAME),
            'passwordSet' => trim((string) ($mail['password'] ?? '')) !== '',
            'configPath' => $path,
            'configExists' => is_file($path),
        ];
    }

    /**
     * Save the Gmail sender address and app password.
     *
     * @return array The fresh current() payload.
     */
    public static function save(array $input, array $actor): array
    {
        $path = self::configPath();
        $username = Validator::email($input['username'] ?? '', true);
        $fromEmail = Validator::email($input['fromEmail'] ?? '', false) ?: $username;
        $fromName = Validator::optionalText($input['fromName'] ?? '', 'Sender name', 120)
            ?: self::DEFAULT_FROM_NAME;

        $existing = self::readFile($path);
        $existingPassword = (string) ($existing['mail']['password'] ?? '');
        if ($existingPassword === '') {
            $effectiveConfig = require __DIR__ . '/../config/config.php';
            $existingPassword = (string) ($effectiveConfig['mail']['password'] ?? '');
        }
        $provided = (string) preg_replace('/\s+/', '', (string) ($input['password'] ?? ''));
        $password = $provided !== '' ? $provided : $existingPassword;

        if ($password === '') {
            throw new HttpException('Enter the 16-character Google App Password.', 422, 'VALIDATION');
        }
        if (!preg_match('/^[A-Za-z0-9]{16,64}$/', $password)) {
            throw new HttpException(
                'The Google App Password must be 16 characters with no spaces.',
                422,
                'VALIDATION',
            );
        }

        $existing['mail'] = array_replace($existing['mail'] ?? [], [
            'driver' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => $username,
            'password' => $password,
            'encryption' => 'tls',
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'timeout' => 15,
        ]);

        self::writeFile($path, $existing);
        Auth::audit($actor, 'Email Delivery Settings Updated', 'Gmail SMTP sender set to ' . $username . '.');

        return self::current();
    }

    /**
     * Send a test message with the saved SMTP settings.
     *
     * @return array{sent: bool, email: string, error: string}
     */
    public static function sendTest(string $to, array $actor): array
    {
        $to = Validator::email($to, true);

        try {
            Mailer::send(
                $to,
                'Guinhawa email test',
                '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#10271b">'
                . '<h1 style="font-size:20px;margin:0 0 12px">Email delivery is working</h1>'
                . '<p style="margin:0">This test message was sent from the Guinhawa Admin Settings page. '
                . 'Account-ready notifications and password recovery codes can now reach staff inboxes.</p>'
                . '</div>',
                "Guinhawa email delivery is working.\n\n"
                . "This test message was sent from the Guinhawa Admin Settings page.\n",
            );
        } catch (\Throwable $error) {
            error_log('[guinhawa] Test email could not be sent: ' . $error->getMessage());

            return [
                'sent' => false,
                'email' => $to,
                'error' => $error->getMessage(),
            ];
        }

        Auth::audit($actor, 'Test Email Sent', 'A test email was sent to ' . $to . '.');

        return ['sent' => true, 'email' => $to, 'error' => ''];
    }

    private static function readFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new HttpException(
                'The protected config file did not return an array: ' . $path,
                500,
                'CONFIG_INVALID',
            );
        }

        return $data;
    }

    private static function writeFile(string $path, array $config): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new HttpException(
                'The protected config folder could not be created: ' . $directory,
                500,
                'CONFIG_WRITE',
            );
        }

        $contents = "<?php\n\n"
            . "// Generated by Guinhawa Settings on " . date('c') . ".\n"
            . "// Keep this file outside the web root.\n\n"
            . "declare(strict_types=1);\n\n"
            . 'return ' . var_export($config, true) . ";\n";

        $temporary = $path . '.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new HttpException(
                'The protected config file could not be written: ' . $path,
                500,
                'CONFIG_WRITE',
            );
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($temporary, 0600);
        }

        if (!@rename($temporary, $path)) {
            // Windows cannot always replace an existing file with rename().
            if (is_file($path)) {
                @unlink($path);
            }
            if (!@rename($temporary, $path)) {
                @unlink($temporary);
                throw new HttpException(
                    'The protected config file could not be replaced: ' . $path,
                    500,
                    'CONFIG_WRITE',
                );
            }
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($path, 0600);
        }
    }
}
