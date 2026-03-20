<?php
// ============================================================
//  MotorGo – Autenticação e Controle de Acesso
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';

// ── Proteção de rota: usuário deve estar logado ───────────────
function requireLogin(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . SITE_URL . '/dozero/login.php');
        exit;
    }
}

// ── Proteção de rota: apenas administradores ──────────────────
function requireAdmin(): void
{
    requireLogin();

    if (($_SESSION['tipo'] ?? '') !== 'administrador') {
        header('Location: ' . SITE_URL . '/dozero/painel.php');
        exit;
    }
}

// ── Proteção de rota: apenas investidores ─────────────────────
function requireInvestidor(): void
{
    requireLogin();

    if (($_SESSION['tipo'] ?? '') !== 'investidor') {
        header('Location: ' . SITE_URL . '/dozero/painel.php');
        exit;
    }
}

// ── Proteção de rota: apenas vendedores ──────────────────────
function requireVendedor(): void
{
    requireLogin();

    if (($_SESSION['tipo'] ?? '') !== 'vendedor') {
        header('Location: ' . SITE_URL . '/dozero/painel.php');
        exit;
    }
}

// ── CSRF: gerar token ─────────────────────────────────────────
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ── CSRF: validar token ───────────────────────────────────────
function validateCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ── Dados do usuário atual ────────────────────────────────────
function getCurrentUser(): array
{
    return [
        'id'     => $_SESSION['usuario_id']  ?? null,
        'nome'   => $_SESSION['nome']        ?? '',
        'email'  => $_SESSION['email']       ?? '',
        'tipo'   => $_SESSION['tipo']        ?? '',
        'foto'   => $_SESSION['foto']        ?? '',
        'status' => $_SESSION['status']      ?? '',
    ];
}

// ── Verificar se está logado ──────────────────────────────────
function isLoggedIn(): bool
{
    return !empty($_SESSION['usuario_id']);
}

// ── Destruir sessão completamente (logout seguro) ─────────────
function destroySession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

// ── Regenerar ID de sessão (após login) ───────────────────────
function regenerateSession(): void
{
    session_regenerate_id(true);
}

// ── Popular sessão após login bem-sucedido ────────────────────
function setUserSession(array $user): void
{
    regenerateSession();

    $_SESSION['usuario_id'] = (int) $user['id'];
    $_SESSION['nome']       = $user['nome']   ?? '';
    $_SESSION['email']      = $user['email']  ?? '';
    $_SESSION['tipo']       = $user['tipo']   ?? '';
    $_SESSION['foto']       = $user['foto']   ?? '';
    $_SESSION['status']     = $user['status'] ?? '';
}
