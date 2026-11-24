<?php
/**
 * Emergency password reset for an existing Admin or staff account.
 *
 * Run from a terminal on the server (never from a browser):
 *
 *   C:\xampp2\php\php.exe backend\scripts\reset_password.php --user=admin
 *   C:\xampp2\php\php.exe backend\scripts\reset_password.php --user=STF-001 --password="YourNewPassword1"
 *
 * With no --password, a strong temporary password is generated and printed
 * once. The script also clears lockouts and revokes old sessions.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This recovery script can only be run from the command line.\n");
}

require __DIR__ . '/../bootstrap.php';

use Guinhawa\Database;
use Guinhawa\Schema;
use Guinhawa\Users;
use Guinhawa\Validator;

function option(string $name): ?string
{
    global $argv;
    foreach ($argv as $argument) {
        if (str_starts_with($argument, "--{$name}=")) {
            return substr($argument, strlen($name) + 3);
        }
    }
    return null;
}

function generatedPassword(): string
{
    $lower = 'abcdefghjkmnpqrstuvwxyz';
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $all = $lower . $upper . $digits;
    $characters = [
        $lower[random_int(0, strlen($lower) - 1)],
        $upper[random_int(0, strlen($upper) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
    ];
    for ($i = 3; $i < 14; $i++) {
        $characters[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($characters) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
    }
    return implode('', $characters);
}

echo "==============================================\n";
echo " Guinhawa - Emergency Password Reset\n";
echo "==============================================\n";

try {
    $identifier = trim((string) (option('user') ?? ''));
    if ($identifier === '') {
        fwrite(STDERR, "Usage: reset_password.php --user=<username|EmployeeID|StaffID> [--password=...]\n");
        exit(1);
    }

    $upper = strtoupper($identifier);
    $row = Database::fetchOne(
        'SELECT * FROM ' . Schema::t('users') . '
         WHERE LOWER(username) = ? OR employee_code = ? OR staff_code = ?
            OR (id = 1 AND ? = "ADMIN-01")
         LIMIT 1',
        [strtolower($identifier), $upper, $upper, $upper],
    );
    if (!$row) {
        fwrite(STDERR, "No account found for: {$identifier}\n");
        exit(1);
    }

    $type = Users::sessionType($row);
    $id = (int) $row['id'];

    $password = option('password');
    $generated = $password === null;
    if ($generated) {
        $password = generatedPassword();
    }
    $password = Validator::password($password, 'New password');

    Database::transaction(static function () use ($id, $password): void {
        Database::execute(
            'UPDATE ' . Schema::t('users') . '
             SET password_hash = ?,
                 failed_login_count = 0,
                 locked_until = NULL,
                 must_change_password = 0,
                 temp_password_expires_at = NULL,
                 session_version = session_version + 1,
                 remember_selector = NULL,
                 remember_token_hash = NULL,
                 remember_session_version = NULL,
                 remember_expires_at = NULL
             WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id],
        );
    });

    echo "\nAccount : " . ($row['full_name'] ?? $row['username']) . " ({$type}, id {$id})\n";
    echo "Login   : " . ($row['username'] ?? $identifier) . "\n";
    if ($type === 'employee') {
        echo "Staff ID: " . ($row['staff_code'] ?? '-') . " | Employee ID: " . ($row['employee_code'] ?? '-') . "\n";
    }
    echo "Password: {$password}" . ($generated ? '  (generated)' : '') . "\n\n";
    echo "All previous sessions were signed out. Clear the sign-in page cookies or wait\n";
    echo "out any 'too many attempts' cooldown, then sign in and change this password.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "\nReset failed: " . $error->getMessage() . "\n");
    exit(1);
}
