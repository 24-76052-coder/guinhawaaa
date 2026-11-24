<?php

declare(strict_types=1);

namespace Guinhawa\Controllers;

use Guinhawa\Auth;
use Guinhawa\Database;
use Guinhawa\Http;
use Guinhawa\HttpException;

/**
 * Per-account appearance preferences.
 *
 * The application has two account tables (`admin` and `employees`), so the
 * preference row is keyed by the pair (user_type, user_id). The unique key
 * keeps exactly one row per account and every write is scoped to the
 * authenticated user; one account can never read or overwrite another's row.
 *
 * The selected colour combination and layout options are stored as JSON in
 * `preferences`. The original one-column-per-option fields are still mirrored
 * so older code and databases keep working, and rows written before the JSON
 * column existed are mapped onto the new shape when they are read.
 *
 * This controller intentionally does not accept custom colours: the interface
 * offers a fixed library of professionally paired combinations, so there is
 * nothing free-form to store.
 */
final class AppearanceController
{
    public const DEFAULTS = [
        'mode' => 'light',
        'theme' => 'forest',
        'fontSize' => 'medium',
        'density' => 'comfortable',
        'corners' => 'rounded',
        'animations' => 'subtle',
        'sidebar' => 'expanded',
        'cardStyle' => 'bordered',
        'background' => 'solid',
    ];

    /** Output order for the stored JSON object. */
    private const ORDER = [
        'mode', 'theme', 'fontSize', 'density', 'corners',
        'animations', 'sidebar', 'cardStyle', 'background',
    ];

    private const OPTIONS = [
        'mode' => ['light', 'dark', 'system'],
        'fontSize' => ['small', 'medium', 'large'],
        'density' => ['compact', 'comfortable', 'spacious'],
        'corners' => ['square', 'rounded'],
        'animations' => ['off', 'subtle', 'smooth'],
        'sidebar' => ['expanded', 'collapsed'],
        'cardStyle' => ['flat', 'bordered', 'elevated'],
        'background' => ['solid', 'gradient'],
    ];

    /** Mirrors shared/js/theme-init.js?v=cleanup-20261122-2. Keep the two lists in sync. */
    private const THEMES = [
        'forest', 'emerald', 'sage', 'olive', 'mint', 'jade', 'deep', 'tropical',
        'ocean', 'midnight', 'arctic', 'royal-blue', 'sky', 'teal',
        'royal-purple', 'lavender', 'violet', 'rose', 'cherry', 'orchid',
        'sunset', 'golden', 'coral', 'desert', 'coffee', 'champagne',
        'slate', 'monochrome', 'minimal', 'charcoal',
    ];

    /** Old preset ids from the previous appearance engine. */
    private const LEGACY_PRESETS = [
        'forest' => 'forest',
        'emerald' => 'emerald',
        'ocean' => 'ocean',
        'midnight' => 'midnight',
        'royal' => 'royal-purple',
        'lavender' => 'lavender',
        'rose' => 'rose',
        'cherry' => 'cherry',
        'sunset' => 'sunset',
        'golden' => 'golden',
        'mint' => 'mint',
        'arctic' => 'arctic',
        'slate' => 'slate',
        'minimal' => 'minimal',
    ];

    /** Old standalone accent ids mapped onto the nearest combination. */
    private const LEGACY_ACCENTS = [
        'forest' => 'forest',
        'emerald' => 'emerald',
        'mint' => 'mint',
        'lime' => 'olive',
        'olive' => 'olive',
        'sage' => 'sage',
        'pine' => 'deep',
        'ocean' => 'ocean',
        'sky' => 'sky',
        'arctic' => 'arctic',
        'cobalt' => 'royal-blue',
        'navy' => 'midnight',
        'teal' => 'teal',
        'royal' => 'royal-purple',
        'violet' => 'violet',
        'indigo' => 'royal-purple',
        'lavender' => 'lavender',
        'plum' => 'orchid',
        'rose' => 'rose',
        'pink' => 'rose',
        'fuchsia' => 'orchid',
        'blush' => 'rose',
        'cherry' => 'cherry',
        'crimson' => 'cherry',
        'scarlet' => 'cherry',
        'coral' => 'coral',
        'sunset' => 'sunset',
        'tangerine' => 'sunset',
        'burnt' => 'coral',
        'peach' => 'champagne',
        'golden' => 'golden',
        'amber' => 'golden',
        'honey' => 'champagne',
        'slate' => 'slate',
        'graphite' => 'charcoal',
        'stone' => 'charcoal',
        'charcoal' => 'charcoal',
        'sand' => 'desert',
    ];

    /**
     * Legacy `accent_color` is an ENUM('forest','fresh','deep','olive'), so
     * the mirror column has to stay inside those four values.
     */
    private const LEGACY_COLUMN = [
        'forest' => 'forest',
        'emerald' => 'forest',
        'sage' => 'forest',
        'olive' => 'olive',
        'mint' => 'fresh',
        'jade' => 'fresh',
        'deep' => 'deep',
        'tropical' => 'fresh',
        'ocean' => 'deep',
        'midnight' => 'deep',
        'arctic' => 'fresh',
        'royal-blue' => 'deep',
        'sky' => 'fresh',
        'teal' => 'deep',
        'royal-purple' => 'deep',
        'lavender' => 'deep',
        'violet' => 'deep',
        'rose' => 'deep',
        'cherry' => 'deep',
        'orchid' => 'deep',
        'sunset' => 'olive',
        'golden' => 'olive',
        'coral' => 'olive',
        'desert' => 'olive',
        'coffee' => 'olive',
        'champagne' => 'olive',
        'slate' => 'deep',
        'monochrome' => 'deep',
        'minimal' => 'deep',
        'charcoal' => 'deep',
    ];

    public static function show(): never
    {
        $user = Auth::requireUser(true);
        Http::json(['appearance' => self::forUser($user)]);
    }

    public static function update(): never
    {
        $user = Auth::requireUser(true);
        $body = Http::body();
        $input = isset($body['preferences']) && is_array($body['preferences'])
            ? $body['preferences']
            : $body;

        $preferences = self::sanitize($input);
        $updatedAt = self::persist($user, $preferences);

        Http::json([
            'appearance' => [
                'preferences' => $preferences,
                'updatedAt' => $updatedAt,
            ],
        ]);
    }

    /** Reset this account back to the Guinhawa defaults. */
    public static function destroy(): never
    {
        $user = Auth::requireUser(true);

        Database::execute(
            'DELETE FROM user_appearance_settings WHERE user_type = ? AND user_id = ?',
            [$user['type'], (int) $user['id']],
        );

        Http::json([
            'appearance' => [
                'preferences' => self::DEFAULTS,
                'updatedAt' => null,
            ],
        ]);
    }

    /** Saved preferences for one authenticated account (defaults when unset). */
    public static function forUser(array $user): array
    {
        $row = Database::fetchOne(
            'SELECT preferences, theme_mode, accent_color, font_size,
                    sidebar_state, interface_density, reduce_animations, updated_at
             FROM user_appearance_settings
             WHERE user_type = ? AND user_id = ?
             LIMIT 1',
            [$user['type'], (int) $user['id']],
        );

        if (!$row) {
            return [
                'preferences' => self::DEFAULTS,
                'updatedAt' => null,
            ];
        }

        $preferences = null;
        if (!empty($row['preferences'])) {
            $decoded = json_decode((string) $row['preferences'], true);
            if (is_array($decoded)) {
                $preferences = self::sanitize($decoded, true);
            }
        }

        if ($preferences === null) {
            $preferences = self::fromLegacy($row);
        }

        return [
            'preferences' => $preferences,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /** Called when an employee account is deleted so no orphan row remains. */
    public static function forgetUser(string $type, int $id): void
    {
        Database::execute(
            'DELETE FROM user_appearance_settings WHERE user_type = ? AND user_id = ?',
            [$type, $id],
        );
    }

    /**
     * Validate one preference object. Invalid fields are rejected so a broken
     * payload cannot leave the account with an unreadable theme. In lenient
     * mode (reading an older saved row) bad values fall back to the defaults
     * instead of failing the whole profile load.
     */
    private static function sanitize(array $input, bool $lenient = false): array
    {
        $preferences = [];

        foreach (self::ORDER as $key) {
            if ($key === 'theme') {
                $theme = $input['theme'] ?? null;
                if (is_string($theme) && in_array($theme, self::THEMES, true)) {
                    $preferences['theme'] = $theme;
                    continue;
                }

                $mapped = self::legacyTheme($input);
                if ($mapped !== null) {
                    $preferences['theme'] = $mapped;
                    continue;
                }

                if ($lenient) {
                    $preferences['theme'] = self::DEFAULTS['theme'];
                    continue;
                }

                throw new HttpException('Please choose a valid colour combination.', 422, 'VALIDATION');
            }

            $allowed = self::OPTIONS[$key];
            $value = $input[$key] ?? self::DEFAULTS[$key];

            if (!is_string($value) || !in_array($value, $allowed, true)) {
                if ($lenient) {
                    $value = self::DEFAULTS[$key];
                } else {
                    throw new HttpException("Please choose a valid {$key} option.", 422, 'VALIDATION');
                }
            }

            $preferences[$key] = $value;
        }

        return $preferences;
    }

    /** Translate a pre-library payload (preset + accent) to a combination. */
    private static function legacyTheme(array $input): ?string
    {
        $preset = $input['preset'] ?? null;
        if (is_string($preset)) {
            if (in_array($preset, self::THEMES, true)) {
                return $preset;
            }
            if (isset(self::LEGACY_PRESETS[$preset])) {
                return self::LEGACY_PRESETS[$preset];
            }
        }

        $accent = $input['accent'] ?? null;
        if (is_string($accent)) {
            if (in_array($accent, self::THEMES, true)) {
                return $accent;
            }
            if (isset(self::LEGACY_ACCENTS[$accent])) {
                return self::LEGACY_ACCENTS[$accent];
            }
        }

        return null;
    }

    /** Map a row written before the JSON column existed. */
    private static function fromLegacy(array $row): array
    {
        $legacyAccent = (string) ($row['accent_color'] ?? 'forest');
        $theme = self::LEGACY_ACCENTS[$legacyAccent] ?? self::DEFAULTS['theme'];
        $fontSize = (string) ($row['font_size'] ?? 'medium');
        $density = (string) ($row['interface_density'] ?? 'comfortable');

        return [
            'mode' => ($row['theme_mode'] ?? 'light') === 'dark' ? 'dark' : 'light',
            'theme' => $theme,
            'fontSize' => in_array($fontSize, ['small', 'medium', 'large'], true) ? $fontSize : 'medium',
            'density' => in_array($density, ['compact', 'spacious'], true) ? $density : 'comfortable',
            'corners' => 'rounded',
            'animations' => !empty($row['reduce_animations']) ? 'off' : 'subtle',
            'sidebar' => ($row['sidebar_state'] ?? 'expanded') === 'collapsed' ? 'collapsed' : 'expanded',
            'cardStyle' => 'bordered',
            'background' => 'solid',
        ];
    }

    private static function persist(array $user, array $preferences): string
    {
        // The mirror columns are narrower than the JSON preferences.
        $mode = in_array($preferences['mode'], ['light', 'dark'], true)
            ? $preferences['mode']
            : 'light';
        $legacyAccent = self::LEGACY_COLUMN[$preferences['theme']] ?? 'forest';
        $density = $preferences['density'] === 'compact' ? 'compact' : 'comfortable';

        Database::execute(
            'INSERT INTO user_appearance_settings
                (user_type, user_id, preferences, theme_mode, accent_color, font_size,
                 sidebar_state, interface_density, reduce_animations)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                preferences = VALUES(preferences),
                theme_mode = VALUES(theme_mode),
                accent_color = VALUES(accent_color),
                font_size = VALUES(font_size),
                sidebar_state = VALUES(sidebar_state),
                interface_density = VALUES(interface_density),
                reduce_animations = VALUES(reduce_animations)',
            [
                $user['type'],
                (int) $user['id'],
                json_encode($preferences, JSON_UNESCAPED_SLASHES),
                $mode,
                $legacyAccent,
                $preferences['fontSize'],
                $preferences['sidebar'],
                $density,
                $preferences['animations'] === 'off' ? 1 : 0,
            ],
        );

        return date('Y-m-d H:i:s');
    }
}
