<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

$acao = trim($_POST['acao'] ?? 'confirmar');

// ── Reenviar código ───────────────────────────────────────────
if ($acao === 'reenviar') {
    $email = trim($_POST['email'] ?? '');
    if (!validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
        exit;
    }
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND status_confirmacao = 'pendente' LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'E-mail não encontrado ou já confirmado.']);
        exit;
    }

    $codigo = generateCode(6);
    $expira = date('Y-m-d H:i:s', strtotime('+2 hours'));
    $stmt = $conn->prepare("UPDATE usuarios SET token_confirmacao = ?, token_expira = ? WHERE id = ?");
    $stmt->bind_param('ssi', $codigo, $expira, $user['id']);
    $stmt->execute();
    $stmt->close();

    $htmlBody = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
  <h2 style='color:#1a1a2e'>Novo código de confirmação – MotorGo</h2>
  <div style='font-size:36px;font-weight:bold;letter-spacing:8px;color:#e63946;text-align:center;padding:20px;background:#f8f9fa;border-radius:8px'>{$codigo}</div>
  <p style='color:#666;font-size:13px'>Este código expira em 2 horas.</p>
</div>";
    sendEmail($email, $user['nome'], 'MotorGo – Novo código de confirmação', $htmlBody);
    echo json_encode(['success' => true, 'message' => 'Novo código enviado para seu e-mail.']);
    exit;
}

// ── Confirmar código ──────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$email  = trim($_POST['email']  ?? '');
$codigo = trim($_POST['codigo'] ?? '');

if (!validateEmail($email) || $codigo === '') {
    echo json_encode(['success' => false, 'message' => 'E-mail e código são obrigatórios.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, tipo, status_cadastro, token_confirmacao, token_expira FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'E-mail não encontrado.']);
    exit;
}
if (strtotime($user['token_expira']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Código expirado. Solicite um novo.']);
    exit;
}
if ($user['token_confirmacao'] !== $codigo) {
    echo json_encode(['success' => false, 'message' => 'Código inválido.']);
    exit;
}

$stmt = $conn->prepare("UPDATE usuarios SET status_confirmacao = 'confirmado', token_confirmacao = NULL WHERE id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

// Preenche campos opcionais que podem não existir no BD legado
$user['email']  = $email;
$user['status'] = $user['status'] ?? '';
$user['foto']   = $user['foto']   ?? '';

setUserSession($user);

echo json_encode(['success' => true, 'message' => 'E-mail confirmado com sucesso!', 'redirect' => '../painel.php']);
