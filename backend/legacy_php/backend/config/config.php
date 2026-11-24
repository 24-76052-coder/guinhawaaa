<?php
/**
 * Database and application configuration.
 *
 * These are the default XAMPP values (MySQL on 127.0.0.1:3306, user root with
 * no password). Change them here if your XAMPP installation uses a password.
 *
 * Hosting without environment variables (shared PHP hosting) can drop a
 * `config.local.php` next to this file that returns overrides, for example:
 *
 *   return [
 *       'db' => ['host' => 'sql123.example.com', 'user' => '...', 'password' => '...'],
 *       'cors' => ['allowed_origins' => ['https://guinhawa.vercel.app']],
 *       'session' => ['same_site' => 'None', 'secure' => '1'],
 *   ];
 *
 * See `deploy/config.local.example.php`. The file is ignored by git.
 */

declare(strict_types=1);

$cookieSecure = getenv('GUINHAWA_COOKIE_SECURE');

$config = [
    'db' => [
        'host' => getenv('GUINHAWA_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('GUINHAWA_DB_PORT') ?: 3306),
        'name' => getenv('GUINHAWA_DB_NAME') ?: 'guinhawa_db',
        'user' => getenv('GUINHAWA_DB_USER') ?: 'root',
        'password' => getenv('GUINHAWA_DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],

    // PHP session cookie used by the API.
    //
    // `Lax` is correct for XAMPP and for any setup where the frontend and API
    // share an origin. When the React app is hosted separately (Vercel calling
    // a PHP host), browsers only send the cookie on cross-site requests when it
    // is `None` + `Secure`, so set both on the API host.
    'session' => [
        'name' => 'guinhawa_session',
        'lifetime' => 60 * 60 * 8,
        'same_site' => getenv('GUINHAWA_COOKIE_SAMESITE') ?: 'Lax',
        // Empty string means "detect HTTPS automatically".
        'secure' => $cookieSecure === false ? '' : $cookieSecure,
    ],

    // Public URL prefix used by the PHP-rendered sign-in pages. The existing
    // React dashboard keeps its own absolute asset paths under the same prefix.
    'app_base_path' => rtrim(getenv('GUINHAWA_BASE_PATH') ?: '/guinhawa_system', '/'),

    // Transactional email for the Admin OTP recovery flow.
    //
    // Production: set GUINHAWA_MAIL_DRIVER=smtp and the SMTP_* variables, or
    // place the credentials in the protected config.local.php file outside
    // htdocs (see backend/config/config.local.example.php). Never commit
    // mailbox passwords to a publicly served file.
    //
    // Local testing: GUINHAWA_MAIL_DRIVER=file writes signed messages to the
    // spool directory outside the web root, so the OTP can be tested without
    // an SMTP server. No OTP is ever written to the PHP error log.
    'mail' => [
        'driver' => strtolower(getenv('GUINHAWA_MAIL_DRIVER') ?: 'smtp'),
        // Gmail SMTP defaults. Put the dedicated mailbox address and its app
        // password in the protected config.local.php outside htdocs.
        'host' => getenv('GUINHAWA_SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int) (getenv('GUINHAWA_SMTP_PORT') ?: 587),
        'username' => getenv('GUINHAWA_SMTP_USER') ?: '',
        'password' => getenv('GUINHAWA_SMTP_PASSWORD') ?: '',
        // tls = STARTTLS on port 587, ssl = implicit TLS on port 465.
        'encryption' => strtolower(getenv('GUINHAWA_SMTP_ENCRYPTION') ?: 'tls'),
        'from_email' => getenv('GUINHAWA_MAIL_FROM') ?: '',
        'from_name' => getenv('GUINHAWA_MAIL_FROM_NAME') ?: 'Guinhawa Agri-Poultry Supply',
        'spool_dir' => getenv('GUINHAWA_MAIL_SPOOL') ?: 'C:/xampp/guinhawa-mail',
        'timeout' => (int) (getenv('GUINHAWA_SMTP_TIMEOUT') ?: 15),
    ],

    // Exact browser origins allowed to call this API with cookies. Leave empty
    // for same-origin hosting (XAMPP) — no CORS headers are sent then.
    'cors' => [
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('GUINHAWA_ALLOWED_ORIGINS') ?: ''),
        ))),
    ],

    'app' => [
        'timezone' => 'Asia/Manila',
        'debug' => filter_var(getenv('GUINHAWA_DEBUG') ?: '0', FILTER_VALIDATE_BOOL),
        // Absolute base used in emailed links. Change this when the
        // system is hosted somewhere other than this local XAMPP folder.
        'url' => rtrim(getenv('GUINHAWA_APP_URL') ?: 'http://localhost/guinhawa_system', '/'),
    ],
];

// Optional overrides for shared hosts that cannot set environment variables.
// Prefer a file outside htdocs so SMTP passwords are never web-accessible.
$secureConfig = getenv('GUINHAWA_CONFIG_FILE') ?: 'C:/xampp/guinhawa-config/config.local.php';
if (is_file($secureConfig)) {
    $overrides = require $secureConfig;
    if (is_array($overrides)) {
        $config = array_replace_recursive($config, $overrides);
    }
}

// Keep supporting the original protected in-project override file.
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $overrides = require $localConfig;
    if (is_array($overrides)) {
        $config = array_replace_recursive($config, $overrides);
    }
}

return $config;
