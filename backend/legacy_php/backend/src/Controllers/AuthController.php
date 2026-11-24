<?php

declare(strict_types=1);

namespace Guinhawa\Controllers;

use Guinhawa\AccountService;
use Guinhawa\AccountEmailVerificationService;
use Guinhawa\Auth;
use Guinhawa\Database;
use Guinhawa\Http;
use Guinhawa\HttpException;
use Guinhawa\LoginService;
use Guinhawa\RecoveryService;
use Guinhawa\StaffActivationService;
use Guinhawa\StaffRecoveryService;
use Guinhawa\Users;
use Guinhawa\Validator;

final class AuthController
{
    public static function login(): never
    {
        $body = Http::body();
        $identifier = trim((string) ($body['identifier'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $result = LoginService::authenticate($identifier, $password);

        if (!isset($result['user'])) {
            $limited = isset($result['retry_after']);
            throw new HttpException(
                (string) ($result['error'] ?? 'Incorrect username or password.'),
                $limited ? 429 : 401,
                $limited ? 'RATE_LIMITED' : 'BAD_CREDENTIALS',
            );
        }

        if (!empty($body['remember'])) {
            Auth::rememberCurrentSession();
        }

        $user = $result['user'];

        Http::json([
            'user' => $user,
            'csrfToken' => Auth::csrfToken(),
            'appearance' => AppearanceController::forUser($user),
        ]);
    }

    public static function me(): never
    {
        $user = Auth::requireUser(true);
        Http::json([
            'user' => $user,
            'csrfToken' => Auth::csrfToken(),
            'appearance' => AppearanceController::forUser($user),
        ]);
    }

    public static function logout(): never
    {
        $user = Auth::user();
        if ($user) {
            Auth::audit($user, 'Signed Out', "{$user['name']} ({$user['employeeId']}) signed out.");
        }
        Auth::logout();
        Http::json(['ok' => true]);
    }

    /**
     * Update the signed-in account's own profile fields.
     *
     * The account identity (username, employee ID and role) stays managed by
     * the administrator. Fields are optional so the avatar can be saved on its
     * own without overwriting text the user is still editing.
     */
    public static function updateProfile(): never
    {
        $user = Auth::requireUser(true);
        $body = Http::body();
        $table = $user['type'] === 'admin' ? 'admin' : 'employees';
        $id = (int) $user['id'];

        $fields = [];
        $params = [];

        if (array_key_exists('fullName', $body)) {
            $fields[] = 'full_name = ?';
            $params[] = Validator::text($body['fullName'], 'Full name', 1, 160);
        }

        if (array_key_exists('email', $body)) {
            throw new HttpException(
                'Add or change your personal email from the Personal email section and verify it before it is saved.',
                422,
                'VERIFICATION_REQUIRED',
            );
        }

        if (array_key_exists('contactNumber', $body)) {
            $fields[] = 'contact_number = ?';
            $params[] = Validator::contact($body['contactNumber']);
        }

        if (array_key_exists('avatar', $body)) {
            $avatar = Validator::imageReference($body['avatar'], 'Profile photo');
            $fields[] = 'avatar = ?';
            $params[] = $avatar === '' ? null : $avatar;
        }

        if (!$fields) {
            throw new HttpException('No profile changes were provided.', 422, 'VALIDATION');
        }

        $params[] = $id;
        Database::execute(
            "UPDATE {$table} SET " . implode(', ', $fields) . ' WHERE id = ?',
            $params,
        );

        Auth::audit($user, 'Profile Updated', "{$user['name']} ({$user['employeeId']}) updated their profile.");

        $fresh = Database::fetchOne("SELECT * FROM {$table} WHERE id = ? LIMIT 1", [$id]);
        $mapped = $user['type'] === 'admin' ? Users::mapAdmin($fresh) : Users::mapEmployee($fresh);

        Http::json(['user' => $mapped, 'csrfToken' => Auth::csrfToken()]);
    }

    public static function accountEmailVerificationStatus(): never
    {
        $user = Auth::requireUser(true);
        Http::json(['verification' => AccountEmailVerificationService::status($user)]);
    }

    public static function requestAccountEmailVerification(): never
    {
        $user = Auth::requireUser(true);
        $body = Http::body();
        Http::json([
            'result' => AccountEmailVerificationService::requestCode($user, $body['email'] ?? ''),
        ]);
    }

    public static function verifyAccountEmail(): never
    {
        $user = Auth::requireUser(true);
        $body = Http::body();
        $result = AccountEmailVerificationService::verifyCode($user, $body['code'] ?? '');
        Http::json([
            'user' => $result['user'],
            'verification' => $result['verification'],
            'csrfToken' => Auth::csrfToken(),
        ]);
    }

    public static function changePassword(): never
    {
        $user = Auth::requireUser(true);
        $body = Http::body();

        $mapped = AccountService::changeOwnPassword(
            $user,
            (string) ($body['currentPassword'] ?? ''),
            (string) ($body['newPassword'] ?? ''),
            (string) ($body['confirmPassword'] ?? ($body['newPassword'] ?? '')),
        );

        Http::json(['user' => $mapped, 'csrfToken' => Auth::csrfToken()]);
    }

    /* --------------------------- Email OTP recovery ------------------------- */

    public static function requestPasswordOtp(): never
    {
        $body = Http::body();
        $result = RecoveryService::requestOtp(
            (string) ($body['identifier'] ?? ($body['email'] ?? '')),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );

        Http::json($result, $result['ok'] ? 200 : (isset($result['retry_after']) ? 429 : 422));
    }

    public static function verifyPasswordOtp(): never
    {
        $body = Http::body();
        $result = RecoveryService::verifyOtp((string) ($body['code'] ?? ''));

        if (!$result['ok']) {
            throw new HttpException((string) ($result['error'] ?? 'That code is invalid.'), 422, 'VALIDATION');
        }

        Http::json(['ok' => true, 'message' => 'Code verified. Choose a new password.']);
    }

    public static function completePasswordReset(): never
    {
        $body = Http::body();
        $result = RecoveryService::completeReset(
            (string) ($body['newPassword'] ?? ''),
            (string) ($body['confirmPassword'] ?? ($body['newPassword'] ?? '')),
        );

        if (!$result['ok']) {
            throw new HttpException((string) ($result['error'] ?? 'The password could not be reset.'), 422, 'VALIDATION');
        }

        Http::json([
            'ok' => true,
            'username' => $result['username'] ?? '',
            'roleLabel' => $result['roleLabel'] ?? '',
            'message' => 'Password updated. Sign in with your new password.',
        ]);
    }

    /* -------------------------- Staff activation ---------------------------- */

    public static function activateStaff(): never
    {
        $body = Http::body();
        $result = StaffActivationService::complete(
            (string) ($body['token'] ?? ''),
            (string) ($body['newPassword'] ?? ''),
            (string) ($body['confirmPassword'] ?? ($body['newPassword'] ?? '')),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );

        if (!$result['ok']) {
            throw new HttpException((string) ($result['error'] ?? 'The account could not be activated.'), 422, 'VALIDATION');
        }

        Http::json([
            'ok' => true,
            'username' => $result['username'] ?? '',
            'roleLabel' => $result['roleLabel'] ?? '',
            'status' => $result['status'] ?? 'active',
            'message' => 'Your password is saved. You can now sign in.',
        ]);
    }

    /* ------------------------- Staff password recovery ---------------------- */

    /**
     * File a staff reset request. The response is always generic so the form
     * cannot be used to discover which accounts exist.
     */
    public static function requestStaffReset(): never
    {
        $body = Http::body();
        $result = StaffRecoveryService::request(
            (string) ($body['identifier'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );

        Http::json($result);
    }

    public static function verifyStaffResetCode(): never
    {
        $body = Http::body();
        $result = StaffRecoveryService::verifyCode(
            (string) ($body['identifier'] ?? ''),
            (string) ($body['code'] ?? ''),
        );

        if (!$result['ok']) {
            throw new HttpException((string) ($result['error'] ?? 'That reset code is invalid.'), 422, 'VALIDATION');
        }

        Http::json(['ok' => true, 'message' => 'Code verified. Choose a new password.']);
    }

    public static function completeStaffReset(): never
    {
        $body = Http::body();
        $result = StaffRecoveryService::completeReset(
            (string) ($body['newPassword'] ?? ''),
            (string) ($body['confirmPassword'] ?? ($body['newPassword'] ?? '')),
        );

        if (!$result['ok']) {
            throw new HttpException((string) ($result['error'] ?? 'The password could not be reset.'), 422, 'VALIDATION');
        }

        Http::json(['ok' => true, 'username' => $result['username'] ?? '']);
    }
}
