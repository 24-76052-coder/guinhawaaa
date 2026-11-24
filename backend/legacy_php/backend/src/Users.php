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

    /** Shape expected by app/src/auth.js and the existing pages. */
    public static function mapAdmin(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'type' => 'admin',
            'code' => 'ADMIN-01',
            'employeeId' => 'ADMIN-01',
            'staffId' => 'ADMIN-01',
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
            'status' => 'active',
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

        return [
            'id' => (int) $row['id'],
            'type' => 'employee',
            'code' => $row['employee_id'],
            'employeeId' => $row['employee_id'],
            'staffId' => $row['staff_id'] ?? $row['employee_id'],
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
