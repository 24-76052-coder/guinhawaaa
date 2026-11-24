<?php

declare(strict_types=1);

namespace Guinhawa;

/**
 * Table-name indirection for the compact schema cutover.
 *
 * During staging, `Schema::t('users')` resolves to `users_new`, so the
 * refactored application can be exercised against the verified staging copies
 * while the legacy tables remain untouched. After the atomic rename, the
 * `compact_staging` setting is turned off and the same code resolves to the
 * canonical 13 table names.
 */
final class Schema
{
    private static string $suffix = '';

    public static function configure(array $config): void
    {
        self::$suffix = !empty($config['db']['compact_staging']) ? '_new' : '';
    }

    public static function t(string $table): string
    {
        return $table . self::$suffix;
    }

    public static function staging(): bool
    {
        return self::$suffix !== '';
    }
}
