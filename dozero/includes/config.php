<?php
// ============================================================
//  MotorGo – Configurações Globais
// ============================================================

// ── Carrega variáveis de ambiente (.env) se disponível ───────
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
                putenv("$key=$val");
            }
        }
    }
}

// ── Banco de Dados ───────────────────────────────────────────
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_USER', $_ENV['DB_USER'] ?? 'u218663118_motorgo');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'MotorGo@2025_Vic');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'u218663118_motorgo');

// ── Site ─────────────────────────────────────────────────────
// Auto-detecta o domínio atual se SITE_URL não estiver definido no .env
$_siteScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_siteHost   = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'motorgo.co';
define('SITE_URL',  $_ENV['SITE_URL']  ?? ($_siteScheme . '://' . $_siteHost));
unset($_siteScheme, $_siteHost);
define('SITE_NAME', 'MotorGo');

// ── E-mail / SMTP ─────────────────────────────────────────────
define('EMAIL_FROM',       $_ENV['EMAIL_USUARIO'] ?? 'noreply@motorgo.co');
define('EMAIL_FROM_NAME',  'MotorGo');
define('EMAIL_SMTP_HOST',  'smtp.hostinger.com');
define('EMAIL_SMTP_USER',  $_ENV['EMAIL_USUARIO'] ?? '');
define('EMAIL_SMTP_PASS',  $_ENV['EMAIL_SENHA']   ?? '');
define('EMAIL_SMTP_PORT',  465);

// ── Uploads ───────────────────────────────────────────────────
// Base URL para fotos do sistema legado – usa o mesmo domínio do site (fotos migradas para cá)
define('LEGACY_PHOTO_BASE_URL', $_ENV['LEGACY_PHOTO_BASE_URL'] ?? SITE_URL);

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
$_uploadScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_uploadHost   = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : parse_url(SITE_URL, PHP_URL_HOST);
define('UPLOAD_URL', $_uploadScheme . '://' . $_uploadHost . '/uploads/');
unset($_uploadScheme, $_uploadHost);

// ── Limites de Upload ─────────────────────────────────────────
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// ── Ambiente ──────────────────────────────────────────────────
define('APP_ENV', 'production'); // 'development' | 'production'
define('APP_DEBUG', false);

// ── Sessão & Timezone ─────────────────────────────────────────
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', 1);  // HTTPS only
    ini_set('session.cookie_samesite', 'Lax');
    session_name('MOTORGO_SESSION');
    session_start();
}
