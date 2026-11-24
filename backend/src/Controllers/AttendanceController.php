<?php

declare(strict_types=1);

namespace Guinhawa\Controllers;

use Guinhawa\Auth;
use Guinhawa\Database;
use Guinhawa\Http;
use Guinhawa\HttpException;
use Guinhawa\Schema;
use Guinhawa\StateRepository;
use Guinhawa\Validator;

final class AttendanceController
{
    public static function clockIn(): never
    {
        $actor = Auth::requireUser();
        $body = Http::body();
        $code = self::resolveCode($actor, $body['code'] ?? '');
        $name = self::resolveName($actor, $code, $body['name'] ?? '');

        $existing = Database::fetchOne(
            'SELECT id FROM ' . Schema::t('attendance') . ' WHERE employee_code = ? AND work_date = CURDATE() LIMIT 1',
            [$code],
        );
        if (!$existing) {
            Database::execute(
                "INSERT INTO " . Schema::t('attendance') . " (employee_code, employee_name, work_date, clock_in, hours, status)
                 VALUES (?, ?, CURDATE(), CURTIME(), 0, IF(CURTIME() > '08:00:00', 'Late', 'Present'))",
                [$code, $name],
            );
            Auth::businessAudit('Attendance Clock In', "{$name} ({$code}) clocked in", $actor);
        }

        Http::json(['state' => StateRepository::snapshot($actor)]);
    }

    public static function clockOut(): never
    {
        $actor = Auth::requireUser();
        $body = Http::body();
        $code = self::resolveCode($actor, $body['code'] ?? '');

        $row = Database::fetchOne(
            'SELECT * FROM ' . Schema::t('attendance') . ' WHERE employee_code = ? AND work_date = CURDATE() LIMIT 1',
            [$code],
        );
        if (!$row) {
            throw new HttpException('No clock-in record was found for today.', 404, 'NOT_FOUND');
        }

        if (!$row['clock_out']) {
            $minutes = max(0, (int) round((time() - strtotime(date('Y-m-d') . ' ' . $row['clock_in'])) / 60));
            $hours = round($minutes / 60, 1);
            Database::execute(
                'UPDATE ' . Schema::t('attendance') . ' SET clock_out = CURTIME(), hours = ? WHERE id = ?',
                [$hours, (int) $row['id']],
            );
            Auth::businessAudit('Attendance Clock Out', "{$row['employee_name']} ({$code}) clocked out", $actor);
        }

        Http::json(['state' => StateRepository::snapshot($actor)]);
    }

    /**
     * Admin manual edit of a day's attendance.
     *
     * The attendance sheet lets an administrator set Present/Late/On Leave/
     * Absent with explicit times, so this upserts the (employee, date) row and
     * derives the hours with the same one-hour break the page shows.
     */
    public static function upsert(): never
    {
        $actor = Auth::requireAdmin();
        $body = Http::body();

        $code = strtoupper(Validator::text($body['code'] ?? '', 'Employee code', 3, 40));
        $date = Validator::date($body['date'] ?? '', 'Work date', true);
        $name = Validator::text($body['name'] ?? '', 'Employee name', 1, 160);
        $note = Validator::optionalText($body['note'] ?? '', 'Note', 255);

        $status = trim((string) ($body['status'] ?? ''));
        $statuses = ['Present', 'Late', 'On Leave', 'Absent', 'Not recorded'];
        if (!in_array($status, $statuses, true)) {
            throw new HttpException('Choose a valid attendance status.', 422, 'VALIDATION');
        }

        $employee = Database::fetchOne(
            'SELECT full_name FROM ' . Schema::t('users') . ' WHERE employee_code = ? LIMIT 1',
            [$code],
        );
        if ($employee) {
            $name = (string) $employee['full_name'];
        }

        $timeIn = self::timeOf($body['time_in'] ?? null, 'Time in');
        $timeOut = self::timeOf($body['time_out'] ?? null, 'Time out');
        $hours = 0.0;

        $existing = Database::fetchOne(
            'SELECT id FROM ' . Schema::t('attendance') . ' WHERE employee_code = ? AND work_date = ? LIMIT 1',
            [$code, $date],
        );

        if ($status === 'Not recorded') {
            if ($existing) {
                Database::execute(
                    'DELETE FROM ' . Schema::t('attendance') . ' WHERE id = ?',
                    [(int) $existing['id']],
                );
                Auth::businessAudit('Attendance Cleared', "{$name} ({$code}) on {$date}", $actor);
            }
            Http::json(['state' => StateRepository::snapshot($actor)]);
        }

        if ($status === 'On Leave' || $status === 'Absent') {
            $timeIn = null;
            $timeOut = null;
        } else {
            if ($timeOut !== null && $timeIn === null) {
                throw new HttpException('Add a time in before a time out.', 422, 'VALIDATION');
            }
            if ($timeIn !== null && $timeOut !== null && $timeOut <= $timeIn) {
                throw new HttpException('Time out must be later than time in.', 422, 'VALIDATION');
            }
            if ($timeIn !== null && $timeOut !== null) {
                $minutes = (int) round((strtotime("1970-01-01 {$timeOut}") - strtotime("1970-01-01 {$timeIn}")) / 60) - 60;
                $hours = max(0.0, round($minutes / 60, 1));
            }
        }

        if ($existing) {
            Database::execute(
                'UPDATE ' . Schema::t('attendance') . ' SET employee_name = ?, clock_in = ?, clock_out = ?, hours = ?, status = ?, note = ?
                 WHERE id = ?',
                [$name, $timeIn, $timeOut, $hours, $status, $note, (int) $existing['id']],
            );
        } else {
            Database::execute(
                'INSERT INTO ' . Schema::t('attendance') . ' (employee_code, employee_name, work_date, clock_in, clock_out, hours, status, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$code, $name, $date, $timeIn, $timeOut, $hours, $status, $note],
            );
        }

        Auth::businessAudit('Attendance Updated', "{$name} ({$code}) on {$date} set to {$status}", $actor);
        Http::json(['state' => StateRepository::snapshot($actor)]);
    }

    private static function timeOf(mixed $value, string $label): ?string
    {
        $clean = trim((string) ($value ?? ''));
        if ($clean === '') {
            return null;
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $clean)) {
            throw new HttpException("{$label} must be a valid time.", 422, 'VALIDATION');
        }
        return strlen($clean) === 5 ? $clean . ':00' : $clean;
    }

    private static function resolveCode(array $actor, mixed $requested): string
    {
        $requested = strtoupper(trim((string) ($requested ?? '')));
        if ($actor['role'] !== 'admin') {
            return $actor['code'];
        }
        if ($requested === '') {
            $requested = 'ADMIN-01';
        }
        return Validator::text($requested, 'Employee code', 3, 40);
    }

    private static function resolveName(array $actor, string $code, mixed $requested): string
    {
        if ($code === 'ADMIN-01' && $actor['role'] === 'admin') {
            return $actor['name'];
        }

        $employee = Database::fetchOne(
            "SELECT full_name FROM " . Schema::t('users') . " WHERE employee_code = ? AND status = 'active' LIMIT 1",
            [$code],
        );
        if ($employee) {
            return $employee['full_name'];
        }

        if ($code === $actor['code']) {
            return $actor['name'];
        }

        $name = trim((string) ($requested ?? ''));
        if ($name === '') {
            throw new HttpException('That employee code does not match an active staff account.', 422, 'VALIDATION');
        }
        return Validator::text($name, 'Employee name', 1, 160);
    }
}
