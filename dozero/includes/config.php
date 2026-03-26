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
// Auto-detecta domínio e subpasta (ex.: /nw) a partir de SCRIPT_NAME.
// Se SITE_URL estiver no .env, esse valor prevalece.
$_siteScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_siteHost   = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'motorgo.co';
$_siteOrigin = $_siteScheme . '://' . $_siteHost;   // ex.: https://motorgo.co

// Detecta subpasta: /nw/painel.php → /nw   |   /painel.php → (vazio)
$_scriptName  = $_SERVER['SCRIPT_NAME'] ?? '';
$_firstSegment = explode('/', ltrim($_scriptName, '/'), 2)[0] ?? '';
$_basePath    = (strpos($_firstSegment, '.php') === false && $_firstSegment !== '')
                ? '/' . $_firstSegment
                : '';
unset($_firstSegment, $_scriptName);

define('SITE_URL',  $_ENV['SITE_URL']  ?? ($_siteOrigin . $_basePath));
define('SITE_NAME', 'MotorGo');

// ── E-mail / SMTP ─────────────────────────────────────────────
define('EMAIL_FROM',       $_ENV['EMAIL_USUARIO'] ?? 'noreply@motorgo.co');
define('EMAIL_FROM_NAME',  'MotorGo');
define('EMAIL_SMTP_HOST',  'smtp.hostinger.com');
define('EMAIL_SMTP_USER',  $_ENV['EMAIL_USUARIO'] ?? '');
define('EMAIL_SMTP_PASS',  $_ENV['EMAIL_SENHA']   ?? '');
define('EMAIL_SMTP_PORT',  465);

// ── Uploads ───────────────────────────────────────────────────
// LEGACY_PHOTO_BASE_URL aponta para a RAIZ do domínio (scheme+host sem subpasta)
// porque o sistema antigo armazena fotos em motorgo.co/uploads/fotos_veiculos/...
// independente da subpasta onde o novo sistema está instalado (ex.: /nw).
define('LEGACY_PHOTO_BASE_URL', $_ENV['LEGACY_PHOTO_BASE_URL'] ?? $_siteOrigin);
unset($_siteScheme, $_siteHost, $_siteOrigin, $_basePath);

// Pasta de uploads do novo sistema (filesystem) e URL correspondente
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// ── Limites de Upload ─────────────────────────────────────────
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// ── Ambiente ──────────────────────────────────────────────────
// Em produção (motorgo.co) erros ficam ocultos; em desenvolvimento ficam visíveis.
$_prodHosts = ['motorgo.co', 'www.motorgo.co'];
$_curHost   = $_SERVER['HTTP_HOST'] ?? '';
define('APP_ENV',   in_array($_curHost, $_prodHosts, true) ? 'production' : 'development');
define('APP_DEBUG', APP_ENV === 'development');
unset($_prodHosts, $_curHost);

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ── Sessão & Timezone ─────────────────────────────────────────
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    $_isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', $_isHttps ? 1 : 0);  // Seguro em HTTPS, funcional em HTTP
    ini_set('session.cookie_samesite', 'Lax');
    unset($_isHttps);
    session_name('MOTORGO_SESSION');
    session_start();
}
