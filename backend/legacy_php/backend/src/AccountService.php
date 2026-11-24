<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Account lifecycle rules shared by the HTML User Management page and the
 * existing JSON API. Only the administrator can create, update, reset or
 * deactivate staff/cashier accounts.
 */
final class AccountService
{
    public static function adminExists(): bool
    {
        return (bool) Database::fetchOne('SELECT id FROM admin WHERE id = 1 LIMIT 1');
    }

    public static function admin(): ?array
    {
        return Database::fetchOne('SELECT * FROM admin WHERE id = 1 LIMIT 1');
    }

    /**
     * Create the one-and-only administrator. MySQL GET_LOCK serialises two
     * simultaneous setup requests; the admin table's fixed primary key and
     * CHECK constraint are the final database-level guard.
     *
     * @return array{recoveryCode: string, admin: array}
     */
    public static function createInitialAdmin(
        string $fullName,
        string $email,
        string $username,
        string $password,
        string $confirmPassword,
        string $ip = '',
    ): array {
        if (self::adminExists()) {
            throw new HttpException('An Admin account already exists. The first-time setup is permanently locked.', 409, 'SETUP_LOCKED');
        }

        $fullName = Validator::text($fullName, 'Full name', 1, 160);
        $email = Validator::email($email, true);
        $username = Validator::username($username);
        $password = Validator::password($password, 'Password');
        if ($password !== $confirmPassword) {
            throw new HttpException('The password confirmation does not match.', 422, 'VALIDATION');
        }

        self::assertIdentityAvailable($username, $email);

        $lock = Database::fetchOne("SELECT GET_LOCK('guinhawa_admin_setup', 10) AS acquired");
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new HttpException('Another setup request is in progress. Please try again.', 409, 'SETUP_BUSY');
        }

        try {
            return Database::transaction(static function () use ($fullName, $email, $username, $password, $ip): array {
                if (self::adminExists()) {
                    throw new HttpException('An Admin account already exists. The first-time setup is permanently locked.', 409, 'SETUP_LOCKED');
                }

                Database::execute(
                    'INSERT INTO admin
                        (id, username, full_name, email, password_hash, must_change_password, password_changed_at)
                     VALUES (1, ?, ?, ?, ?, 0, NOW())',
                    [$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT)],
                );

                $recoveryCode = self::insertRecoveryCode($ip);

                Auth::audit(
                    ['type' => 'admin', 'id' => 1, 'name' => $fullName],
                    'Initial Admin Account Created',
                    'The first-time setup created the sole Administrator account.',
                    'ADMIN-01',
                );

                return [
                    'recoveryCode' => $recoveryCode,
                    'admin' => Users::mapAdmin(Database::fetchOne('SELECT * FROM admin WHERE id = 1 LIMIT 1') ?? []),
                ];
            });
        } finally {
            Database::fetchOne("SELECT RELEASE_LOCK('guinhawa_admin_setup') AS released");
        }
    }

    /** Issue a fresh single-use backup recovery code and invalidate old ones. */
    public static function generateRecoveryCode(array $actor, string $ip = ''): string
    {
        $admin = self::admin();
        if (!$admin) {
            throw new HttpException('The Admin account no longer exists.', 404, 'NOT_FOUND');
        }

        return Database::transaction(static function () use ($actor, $ip): string {
            Database::execute(
                'UPDATE admin_recovery_codes SET used_at = NOW() WHERE admin_id = 1 AND used_at IS NULL',
            );
            $code = self::insertRecoveryCode($ip);
            Auth::audit(
                $actor,
                'Backup Recovery Code Generated',
                'A replacement single-use backup recovery code was generated.',
                'ADMIN-01',
            );
            return $code;
        });
    }

    public static function listEmployees(string $search = ''): array
    {
        $where = [
            "role IN ('inventory-staff', 'cashier', 'counter-staff', 'staff')",
            'deleted_at IS NULL',
        ];
        $params = [];

        $search = trim($search);
        if ($search !== '') {
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $where[] = '(full_name LIKE ? OR employee_id LIKE ? OR username LIKE ? OR email LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        $rows = Database::fetchAll(
            'SELECT id, employee_id, staff_id, username, full_name, email, email_verified, contact_number, role,
                    date_joined, status, account_status, must_change_password, temp_password_expires_at,
                    last_login_at, password_changed_at, created_at, deleted_at, deleted_by
             FROM employees
             WHERE ' . implode(' AND ', $where) . "
             ORDER BY status = 'active' DESC, status = 'pending' DESC, full_name ASC",
            $params,
        );

        return array_map([Users::class, 'mapEmployee'], $rows);
    }

    /**
     * Staff records an Admin deleted. The rows are kept as a read-only backup
     * so sales, attendance, audit history and personal details stay intact.
     */
    public static function archivedEmployees(): array
    {
        $rows = Database::fetchAll(
            "SELECT id, employee_id, staff_id, username, full_name, email, email_verified, contact_number, role,
                    date_joined, status, account_status, must_change_password, temp_password_expires_at, last_login_at,
                    password_changed_at, created_at, deleted_at, deleted_by
             FROM employees
             WHERE deleted_at IS NOT NULL
               AND role IN ('inventory-staff', 'cashier', 'counter-staff', 'staff')
             ORDER BY deleted_at DESC, id DESC",
        );

        return array_map([Users::class, 'mapEmployee'], $rows);
    }

    public static function findEmployee(int $id): array
    {
        $row = Database::fetchOne('SELECT * FROM employees WHERE id = ? LIMIT 1', [$id]);
        if (!$row) {
            throw new HttpException('That account no longer exists.', 404, 'NOT_FOUND');
        }
        if (!empty($row['deleted_at'])) {
            throw new HttpException(
                'That staff account was deleted. Restore it from Archived staff first.',
                409,
                'ARCHIVED',
            );
        }
        if (!in_array((string) $row['role'], Validator::STAFF_ROLES, true)) {
            throw new HttpException('Administrator accounts are not managed from User Management.', 400, 'VALIDATION');
        }
        return $row;
    }

    /**
     * Create an active staff account with an Admin-supplied default password.
     *
     * The account must replace that password on first sign-in
     * (`must_change_password = 1`). Only an Admin reaches this method; the
     * controller enforces that before calling it. The Admin-entered personal
     * email is registered and verified immediately for password recovery.
     *
     * @return array{account: array}
     */
    public static function createEmployee(array $input, array $actor): array
    {
        $fullName = Validator::text($input['fullName'] ?? '', 'Full name', 1, 160);
        $email = Validator::email($input['email'] ?? '', true);
        $contact = Validator::contact($input['contactNumber'] ?? ($input['phone'] ?? ''));
        $role = Validator::staffRole($input['role'] ?? '');
        $password = Validator::password($input['password'] ?? '', 'Default password');
        $status = Validator::status($input['status'] ?? 'active');
        if ($status === 'pending') {
            $status = 'active';
        }

        $requestedUsername = trim((string) ($input['username'] ?? ''));
        $username = $requestedUsername !== ''
            ? Validator::username($requestedUsername)
            : self::uniqueUsername($email);

        $dateJoined = date('Y-m-d');
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        self::assertIdentityAvailable($username, $email);

        $employeeId = Database::transaction(static function () use (
            $fullName,
            $username,
            $email,
            $passwordHash,
            $contact,
            $role,
            $dateJoined,
            $status,
            $actor,
        ): string {
            $employeeId = self::nextStaffId();

            Database::execute(
                'INSERT INTO employees
                    (employee_id, staff_id, username, full_name, email, email_verified, email_verified_at,
                     contact_number, role, date_joined, status, password_hash, must_change_password,
                     temp_password_expires_at, created_by)
                 VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, 1, NULL, ?)',
                [
                    $employeeId,
                    $employeeId,
                    $username,
                    $fullName,
                    $email,
                    $contact,
                    $role,
                    $dateJoined,
                    $status,
                    $passwordHash,
                    (string) ($actor['code'] ?? 'ADMIN-01'),
                ],
            );

            return $employeeId;
        });

        $created = Database::fetchOne('SELECT * FROM employees WHERE employee_id = ? LIMIT 1', [$employeeId]);
        $mapped = Users::mapEmployee($created);

        Auth::audit(
            $actor,
            'Employee Account Created',
            "{$fullName} ({$employeeId}) created as {$mapped['roleLabel']} with an Admin-assigned default password. "
                . 'The staff member must change it on first sign-in.',
            $employeeId,
        );

        return [
            'account' => $mapped,
        ];
    }

    public static function updateEmployee(int $id, array $input, array $actor): array
    {
        $current = self::findEmployee($id);
        $fullName = Validator::text($input['fullName'] ?? '', 'Full name', 1, 160);
        $username = Validator::username($input['username'] ?? ($current['username'] ?? ''));
        $email = Validator::email($input['email'] ?? ($current['email'] ?? ''), true);
        $role = Validator::staffRole($input['role'] ?? '');
        $contact = Validator::contact($input['contactNumber'] ?? ($input['phone'] ?? ($current['contact_number'] ?? '')));
        $status = Validator::status($input['status'] ?? ($current['status'] ?? 'active'));

        $emailChanged = strcasecmp((string) ($current['email'] ?? ''), (string) ($email ?? '')) !== 0;
        self::assertIdentityAvailable($username, $email, $id);

        Database::execute(
            'UPDATE employees
             SET full_name = ?, username = ?, email = ?, email_verified = ?, email_verified_at = ?,
                 contact_number = ?, role = ?, status = ?
             WHERE id = ?',
            [
                $fullName,
                $username,
                $email,
                // An Admin-entered address is registered and trusted for
                // sign-in and password recovery, so it is verified on entry.
                1,
                $emailChanged ? date('Y-m-d H:i:s') : ($current['email_verified_at'] ?? date('Y-m-d H:i:s')),
                $contact,
                $role,
                $status,
                $id,
            ],
        );

        if ($emailChanged) {
            // Clear any legacy staff-only verification row left by older installs.
            Database::execute('DELETE FROM staff_email_verifications WHERE employee_id = ?', [$id]);
            Database::execute(
                "DELETE FROM account_email_verifications WHERE user_type = 'employee' AND user_id = ?",
                [$id],
            );
            Database::execute(
                "UPDATE password_reset_requests SET consumed_at = NOW()
                 WHERE user_type = 'employee' AND user_id = ? AND channel = 'email'
                   AND consumed_at IS NULL AND reset_completed_at IS NULL",
                [$id],
            );
            Database::execute(
                'UPDATE account_activation_tokens SET used_at = NOW() WHERE employee_id = ? AND used_at IS NULL',
                [$id],
            );
        }

        if ($status !== 'pending') {
            Database::execute(
                'UPDATE account_activation_tokens SET used_at = NOW() WHERE employee_id = ? AND used_at IS NULL',
                [$id],
            );
        }

        if ($status !== 'active' && $current['status'] === 'active') {
            Auth::invalidateSessions('employee', $id);
        }

        $updated = Database::fetchOne('SELECT * FROM employees WHERE id = ? LIMIT 1', [$id]);
        $mapped = Users::mapEmployee($updated);
        Auth::audit(
            $actor,
            'Employee Account Updated',
            "{$fullName} ({$updated['employee_id']}) updated - {$mapped['roleLabel']}, {$status}.",
            (string) $updated['employee_id'],
        );

        return $mapped;
    }

    public static function setStatus(int $id, string $status, array $actor): array
    {
        $current = self::findEmployee($id);
        $status = Validator::status($status);

        if ($current['status'] === $status) {
            return Users::mapEmployee($current);
        }

        Database::execute('UPDATE employees SET status = ? WHERE id = ?', [$status, $id]);

        if ($status !== 'active') {
            Auth::invalidateSessions('employee', $id);
        }

        $updated = Database::fetchOne('SELECT * FROM employees WHERE id = ? LIMIT 1', [$id]);
        Auth::audit(
            $actor,
            $status === 'active'
                ? 'Employee Account Reactivated'
                : ($status === 'pending' ? 'Employee Account Set Pending' : 'Employee Account Deactivated'),
            "{$updated['full_name']} ({$updated['employee_id']}) was " .
                ($status === 'active' ? 'reactivated' : ($status === 'pending' ? 'set to Pending' : 'deactivated')) . '.',
            (string) $updated['employee_id'],
        );

        if ($status !== 'pending') {
            Database::execute(
                'UPDATE account_activation_tokens SET used_at = NOW() WHERE employee_id = ? AND used_at IS NULL',
                [$id],
            );
        }

        return Users::mapEmployee($updated);
    }

    /**
     * Delete a staff account without losing its information.
     *
     * The employee row is kept as an archived backup (personal details, role,
     * shift, joined date, last activity, audit history) and access is revoked
     * immediately. Sales and attendance keep referencing the same record.
     */
    public static function archiveEmployee(int $id, array $actor): array
    {
        $current = self::findEmployee($id);

        Database::transaction(static function () use ($id, $actor): void {
            Database::execute(
                'UPDATE employees
                 SET deleted_at = NOW(), deleted_by = ?, status = ?
                 WHERE id = ?',
                [self::actorLabel($actor), 'inactive', $id],
            );

            Database::execute(
                'UPDATE account_activation_tokens SET used_at = NOW()
                 WHERE employee_id = ? AND used_at IS NULL',
                [$id],
            );
        });

        Auth::invalidateSessions('employee', $id);

        Auth::audit(
            $actor,
            'Employee Account Deleted',
            "{$current['full_name']} ({$current['employee_id']}) was moved to Archived staff. "
                . 'All details and linked records were kept as a backup.',
            (string) $current['employee_id'],
        );

        $archived = Database::fetchOne('SELECT * FROM employees WHERE id = ? LIMIT 1', [$id]);
        return Users::mapEmployee($archived ?? $current);
    }

    /** Bring a deleted staff record back into the team directory. */
    public static function restoreEmployee(int $id, array $actor): array
    {
        $row = Database::fetchOne(
            'SELECT * FROM employees WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1',
            [$id],
        );
        if (!$row) {
            throw new HttpException('That staff record is not in the archive.', 404, 'NOT_FOUND');
        }
        if (!in_array((string) $row['role'], Validator::STAFF_ROLES, true)) {
            throw new HttpException('Administrator accounts are not managed from User Management.', 400, 'VALIDATION');
        }

        Database::execute(
            'UPDATE employees SET deleted_at = NULL, deleted_by = NULL WHERE id = ?',
            [$id],
        );

        Auth::audit(
            $actor,
            'Employee Account Restored',
            "{$row['full_name']} ({$row['employee_id']}) was restored from Archived staff and stays inactive until an Admin activates it.",
            (string) $row['employee_id'],
        );

        $restored = Database::fetchOne('SELECT * FROM employees WHERE id = ? LIMIT 1', [$id]);
        return Users::mapEmployee($restored ?? $row);
    }

    private static function actorLabel(array $actor): string
    {
        $label = trim((string) ($actor['name'] ?? $actor['fullName'] ?? ''));
        if ($label === '') {
            $label = 'Administrator';
        }
        return mb_substr($label, 0, 160);
    }

    /**
     * Assign a new temporary/default password and invalidate every existing
     * session for that account. The Admin may supply the password, or one is
     * generated and shown to the Admin exactly once. The plain-text password
     * is never stored.
     *
     * @return array{password: string, account: array, expiresAt: null}
     */
    public static function resetEmployeePassword(int $id, array $actor, string $password = ''): array
    {
        $current = self::findEmployee($id);
        $temporary = trim($password) !== ''
            ? Validator::password($password, 'Temporary password')
            : Validator::temporaryPassword();

        Database::execute(
            'UPDATE employees
             SET password_hash = ?, must_change_password = 1, temp_password_expires_at = NULL,
                 password_changed_at = NOW(), failed_login_count = 0, locked_until = NULL
             WHERE id = ?',
            [password_hash($temporary, PASSWORD_DEFAULT), $id],
        );

        Auth::invalidateSessions('employee', $id);

        Auth::audit(
            $actor,
            'Employee Password Reset',
            "{$current['full_name']} ({$current['employee_id']}) received a new temporary password. "
                . 'The staff member must change it on the next sign-in.',
            (string) $current['employee_id'],
        );

        $fresh = Database::fetchOne('SELECT * FROM employees WHERE id = ? LIMIT 1', [$id]);
        return [
            'password' => $temporary,
            'account' => Users::mapEmployee($fresh),
            'expiresAt' => null,
        ];
    }

    /** Change the signed-in account's own password. */
    public static function changeOwnPassword(
        array $user,
        string $currentPassword,
        string $newPassword,
        string $confirmPassword,
    ): array {
        if ($currentPassword === '') {
            throw new HttpException('Enter your current password.', 422, 'VALIDATION');
        }
        $newPassword = Validator::password($newPassword, 'New password');
        if ($newPassword !== $confirmPassword) {
            throw new HttpException('The new password confirmation does not match.', 422, 'VALIDATION');
        }

        $table = $user['type'] === 'admin' ? 'admin' : 'employees';
        $id = (int) $user['id'];
        $row = Database::fetchOne("SELECT * FROM {$table} WHERE id = ? LIMIT 1", [$id]);
        if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
            throw new HttpException('Your current password is incorrect.', 400, 'BAD_PASSWORD');
        }
        if (password_verify($newPassword, (string) $row['password_hash'])) {
            throw new HttpException('Choose a password that is different from your current password.', 422, 'VALIDATION');
        }

        Database::execute(
            "UPDATE {$table}
             SET password_hash = ?, must_change_password = 0, temp_password_expires_at = NULL,
                 password_changed_at = NOW(), failed_login_count = 0, locked_until = NULL
             WHERE id = ?",
            [password_hash($newPassword, PASSWORD_DEFAULT), $id],
        );

        // Revoke other devices but keep this session signed in.
        Auth::invalidateSessions($user['type'], $id, true);

        Auth::audit(
            $user,
            'Password Changed',
            ($user['type'] === 'admin' ? 'Administrator' : 'Employee') . ' changed their own password.',
            (string) ($user['employeeId'] ?? 'ADMIN-01'),
        );

        $fresh = Database::fetchOne("SELECT * FROM {$table} WHERE id = ? LIMIT 1", [$id]);
        return $user['type'] === 'admin' ? Users::mapAdmin($fresh) : Users::mapEmployee($fresh);
    }

    public static function assertIdentityAvailable(string $username, ?string $email, ?int $exceptEmployeeId = null): void
    {
        $sql = 'SELECT id, username, email FROM admin WHERE username = ?';
        $params = [$username];
        if ($email !== null) {
            $sql .= ' OR email = ?';
            $params[] = $email;
        }
        $admin = Database::fetchOne($sql . ' LIMIT 1', $params);
        if ($admin) {
            if (strcasecmp((string) $admin['username'], $username) === 0) {
                throw new HttpException('That username is already in use.', 409, 'CONFLICT');
            }
            throw new HttpException('That email address is already in use.', 409, 'CONFLICT');
        }

        $params = [$username];
        $sql = 'SELECT id, username, email, deleted_at FROM employees WHERE username = ?';
        if ($email !== null) {
            $sql = 'SELECT id, username, email, deleted_at FROM employees WHERE (username = ? OR email = ?)';
            $params[] = $email;
        }
        if ($exceptEmployeeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptEmployeeId;
        }
        $sql .= ' LIMIT 1';

        $employee = Database::fetchOne($sql, $params);
        if ($employee) {
            $archived = !empty($employee['deleted_at']);
            if (strcasecmp((string) $employee['username'], $username) === 0) {
                throw new HttpException(
                    $archived
                        ? 'That username belongs to an archived staff record. Restore it from Archived staff instead.'
                        : 'That username is already in use.',
                    409,
                    $archived ? 'ARCHIVED' : 'CONFLICT',
                );
            }
            throw new HttpException(
                $archived
                    ? 'That email address belongs to an archived staff record. Restore it from Archived staff instead.'
                    : 'That email address is already in use.',
                409,
                $archived ? 'ARCHIVED' : 'CONFLICT',
            );
        }
    }

    /**
     * Generate the next Staff ID. STF-001, STF-002, ... is shared by every
     * role so the Admin has one unambiguous identifier for each person.
     */
    private static function nextStaffId(): string
    {
        $rows = Database::fetchAll(
            "SELECT staff_id, employee_id FROM employees
             WHERE staff_id LIKE 'STF-%' OR employee_id LIKE 'STF-%'",
        );

        $highest = 0;
        foreach ($rows as $row) {
            foreach (['staff_id', 'employee_id'] as $column) {
                if (preg_match('/^STF-(\d+)$/i', (string) ($row[$column] ?? ''), $match)) {
                    $highest = max($highest, (int) $match[1]);
                }
            }
        }

        return 'STF-' . str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    }

    /** Derive a unique username from the registered email address. */
    private static function uniqueUsername(string $email): string
    {
        $local = strstr($email, '@', true);
        $base = strtolower((string) ($local === false ? 'staff' : $local));
        $base = preg_replace('/[^a-z0-9._-]+/', '.', $base) ?? 'staff';
        $base = trim($base, '.-_');
        if (strlen($base) < 3) {
            $base = 'staff';
        }
        $base = substr($base, 0, 50);

        $candidate = $base;
        $suffix = 1;
        while (self::identityExists($candidate)) {
            $suffix += 1;
            $candidate = substr($base, 0, 50) . $suffix;
        }

        return $candidate;
    }

    private static function identityExists(string $username): bool
    {
        $admin = Database::fetchOne('SELECT id FROM admin WHERE username = ? LIMIT 1', [$username]);
        if ($admin) {
            return true;
        }
        $employee = Database::fetchOne('SELECT id FROM employees WHERE username = ? LIMIT 1', [$username]);
        return (bool) $employee;
    }

    private static function insertRecoveryCode(string $ip): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups = [];
        for ($group = 0; $group < 4; $group++) {
            $chunk = '';
            for ($index = 0; $index < 4; $index++) {
                $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $chunk;
        }
        $code = 'GQ-' . implode('-', $groups);

        Database::execute(
            'INSERT INTO admin_recovery_codes (admin_id, code_hash, created_ip) VALUES (1, ?, ?)',
            [hash('sha256', $code), substr($ip, 0, 45)],
        );

        return $code;
    }
}
