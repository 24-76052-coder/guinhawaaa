<?php

declare(strict_types=1);

namespace Guinhawa\Controllers;

use Guinhawa\AccountService;
use Guinhawa\Auth;
use Guinhawa\Database;
use Guinhawa\Http;
use Guinhawa\HttpException;
use Guinhawa\Schema;
use Guinhawa\StaffActivationService;
use Guinhawa\StaffRecoveryService;
use Guinhawa\Users;
use Guinhawa\Validator;

final class EmployeeController
{
    public static function directory(): never
    {
        Auth::requireUser();
        $rows = Database::fetchAll(
            "SELECT *, employee_code AS employee_id
             FROM " . Schema::t('users') . "
             WHERE status = 'active' AND deleted_at IS NULL
               AND role IN ('inventory-staff', 'cashier', 'counter-staff', 'staff')
             ORDER BY full_name ASC",
        );
        Http::json(['employees' => array_map([Users::class, 'mapEmployee'], $rows)]);
    }

    public static function index(): never
    {
        Auth::requireAdmin();
        $search = Http::query('search');
        $role = Http::query('role');
        $status = Http::query('status');

        $where = [
            "role IN ('inventory-staff', 'cashier', 'counter-staff', 'staff')",
            'deleted_at IS NULL',
        ];
        $params = [];

        if ($role !== '') {
            if (!in_array($role, Validator::STAFF_ROLES, true)) {
                throw new HttpException('Choose a valid staff role filter.', 422, 'VALIDATION');
            }
            $where[] = 'role = ?';
            $params[] = $role;
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = Validator::status($status);
        }
        if ($search !== '') {
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $where[] = '(full_name LIKE ? OR employee_code LIKE ? OR username LIKE ? OR email LIKE ? OR contact_number LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term);
        }

        $rows = Database::fetchAll(
            'SELECT *, employee_code AS employee_id
             FROM ' . Schema::t('users') . '
             WHERE ' . implode(' AND ', $where) . "
             ORDER BY status = 'active' DESC, status = 'pending' DESC, full_name ASC",
            $params,
        );

        Http::json(['employees' => array_map([Users::class, 'mapEmployee'], $rows)]);
    }

    public static function store(): never
    {
        $actor = Auth::requireAdmin();
        $body = Http::body();

        $result = AccountService::createEmployee($body, $actor);

        Http::json([
            'account' => $result['account'],
            'message' => 'Staff account created. Share the default password securely; the staff member must change it on first sign-in.',
        ], 201);
    }

    public static function update(int $id): never
    {
        $actor = Auth::requireAdmin();

        $account = AccountService::updateEmployee($id, Http::body(), $actor);

        Http::json(['account' => $account]);
    }

    public static function setStatus(int $id): never
    {
        $actor = Auth::requireAdmin();
        $status = Validator::status(Http::body()['status'] ?? '');

        $account = AccountService::setStatus($id, $status, $actor);

        Http::json(['account' => $account]);
    }

    public static function resetPassword(int $id): never
    {
        $actor = Auth::requireAdmin();
        $body = Http::body();
        $result = AccountService::resetEmployeePassword($id, $actor, (string) ($body['password'] ?? ''));

        Http::json([
            'temporaryPassword' => $result['password'],
            'expiresAt' => $result['expiresAt'],
            'account' => $result['account'],
            'message' => 'Password reset. The staff member must change it on the next sign-in.',
        ]);
    }

    /**
     * Delete a staff account. The record is archived, never erased, so sales,
     * attendance and audit history keep pointing at the same person.
     */
    public static function destroy(int $id): never
    {
        $actor = Auth::requireAdmin();
        $account = AccountService::archiveEmployee($id, $actor);

        Http::json([
            'removed' => $account,
            'archived' => true,
            'message' => 'The staff account was deleted and kept in Archived staff as a backup.',
        ]);
    }

    public static function archived(): never
    {
        Auth::requireAdmin();

        Http::json(['archived' => AccountService::archivedEmployees()]);
    }

    public static function restore(int $id): never
    {
        $actor = Auth::requireAdmin();
        $account = AccountService::restoreEmployee($id, $actor);

        Http::json([
            'account' => $account,
            'message' => 'The staff record was restored. Activate the account when the staff member is ready to sign in.',
        ]);
    }

    /* -------------------------- Account activation -------------------------- */

    public static function pendingActivations(): never
    {
        Auth::requireAdmin();

        Http::json(['activations' => StaffActivationService::pendingAccounts()]);
    }

    public static function resendActivation(int $id): never
    {
        $actor = Auth::requireAdmin();

        $result = StaffActivationService::resend(
            $id,
            $actor,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );

        Http::json([
            'activation' => $result,
            'message' => $result['sent']
                ? 'A new activation link was emailed to ' . $result['email'] . '.'
                : $result['error'],
        ]);
    }

    public static function cancelActivation(int $id): never
    {
        $actor = Auth::requireAdmin();

        Http::json(StaffActivationService::cancel($id, $actor));
    }

    /* ----------------------- Staff password requests ------------------------ */

    public static function passwordResetRequests(): never
    {
        Auth::requireAdmin();

        Http::json(['requests' => StaffRecoveryService::pendingRequests()]);
    }

    public static function issuePasswordResetCode(int $id): never
    {
        $actor = Auth::requireAdmin();

        Http::json(StaffRecoveryService::issueCode($id, $actor));
    }

    public static function dismissPasswordResetRequest(int $id): never
    {
        $actor = Auth::requireAdmin();

        Http::json(StaffRecoveryService::dismiss($id, $actor));
    }

    public static function auditLogs(): never
    {
        Auth::requireAdmin();
        $rows = Database::fetchAll(
            'SELECT * FROM ' . Schema::t('audit_logs') . '
             WHERE category = "account"
             ORDER BY created_at DESC, id DESC LIMIT 300',
        );

        $logs = array_map(static function (array $row): array {
            $timestamp = strtotime((string) $row['created_at']);
            return [
                'id' => 'ACC-' . $row['id'],
                'user' => $row['actor'] ?: 'System',
                'action' => $row['action'],
                'details' => $row['target_employee_id']
                    ? $row['details'] . ' [' . $row['target_employee_id'] . ']'
                    : $row['details'],
                'date' => $timestamp ? date('M j Y', $timestamp) : (string) $row['created_at'],
                'time' => $timestamp ? date('h:i A', $timestamp) : '',
            ];
        }, $rows);

        Http::json(['logs' => $logs]);
    }
}
