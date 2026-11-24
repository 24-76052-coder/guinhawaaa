<?php

declare(strict_types=1);

namespace Guinhawa;

final class Users
{
    public const ROLE_LABELS = [
        'admin' => 'Admin',
        'inventory-staff' => 'Inventory Staff',
        'cashier' => 'Cashier',
        'counter-staff' => 'Cashier',
        'staff' => 'Cashier',
    ];

    /** Every employee follows the same fixed working schedule. */
    public const STANDARD_WORK_SCHEDULE = '8:00 AM - 5:00 PM';

    /** Legacy-compatible session/account type derived from the unified role. */
    public static function sessionType(array $row): string
    {
        return (string) ($row['role'] ?? '') === 'admin' ? 'admin' : 'employee';
    }

    /** Map any row from the unified `users` table to the API user shape. */
    public static function mapUser(array $row): array
    {
        return self::sessionType($row) === 'admin'
            ? self::mapAdmin($row)
            : self::mapEmployee($row);
    }

    /** Shape expected by app/src/auth.js and the existing pages. */
    public static function mapAdmin(array $row): array
    {
        $code = (string) ($row['employee_code'] ?? $row['employee_id'] ?? 'ADMIN-01');

        return [
            'id' => (int) $row['id'],
            'type' => 'admin',
            'code' => $code,
            'employeeId' => $code,
            'staffId' => $row['staff_code'] ?? $row['staff_id'] ?? $code,
            'username' => $row['username'],
            'loginUsername' => $row['username'],
            'name' => $row['full_name'],
            'fullName' => $row['full_name'],
            'email' => $row['email'] ?? '',
            'emailVerified' => (bool) ($row['email_verified'] ?? false),
            'emailVerifiedAt' => $row['email_verified_at'] ?? null,
            'contactNumber' => $row['contact_number'] ?? '',
            'role' => 'admin',
            'dbRole' => 'admin',
            'roleLabel' => self::ROLE_LABELS['admin'],
            'status' => $row['status'] ?? 'active',
            'avatar' => ($row['avatar'] ?? '') ?: Validator::initials($row['full_name']),
            'mustChangePassword' => (bool) $row['must_change_password'],
            'tempPasswordExpiresAt' => $row['temp_password_expires_at'] ?? null,
            'lastLoginAt' => $row['last_login_at'] ?? null,
            'lastPasswordChange' => $row['password_changed_at'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * The React application expects cashier accounts to use the
     * `counter-staff` route key. The live database stores the same role as
     * `cashier`, so normalise the API shape without rewriting existing rows.
     */
    public static function mapEmployee(array $row): array
    {
        $dbRole = (string) $row['role'];
        $role = in_array($dbRole, ['cashier', 'counter-staff', 'staff'], true)
            ? 'counter-staff'
            : $dbRole;
        $code = (string) ($row['employee_code'] ?? $row['employee_id'] ?? '');
        $staffCode = $row['staff_code'] ?? $row['staff_id'] ?? null;

        return [
            'id' => (int) $row['id'],
            'type' => 'employee',
            'code' => $code,
            'employeeId' => $code,
            'staffId' => $staffCode ?? $code,
            'username' => $row['username'],
            'loginUsername' => $row['username'],
            'name' => $row['full_name'],
            'fullName' => $row['full_name'],
            'email' => $row['email'] ?? '',
            'emailVerified' => (bool) ($row['email_verified'] ?? false),
            'emailVerifiedAt' => $row['email_verified_at'] ?? null,
            'contactNumber' => $row['contact_number'] ?? '',
            'role' => $role,
            'dbRole' => $dbRole,
            'roleLabel' => self::ROLE_LABELS[$dbRole] ?? self::ROLE_LABELS[$role] ?? $dbRole,
            'status' => $row['status'],
            'accountStatus' => $row['account_status'] ?? $row['status'],
            'workShift' => self::STANDARD_WORK_SCHEDULE,
            'dateJoined' => $row['date_joined'] ?? null,
            'avatar' => ($row['avatar'] ?? '') ?: Validator::initials($row['full_name']),
            'mustChangePassword' => (bool) $row['must_change_password'],
            'tempPasswordExpiresAt' => $row['temp_password_expires_at'] ?? null,
            'lastLoginAt' => $row['last_login_at'] ?? null,
            'lastPasswordChange' => $row['password_changed_at'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
            'deletedAt' => $row['deleted_at'] ?? null,
            'deletedBy' => $row['deleted_by'] ?? null,
        ];
    }
}
