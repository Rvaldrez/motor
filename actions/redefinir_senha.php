<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$token           = trim($_POST['token']           ?? '');
$senha           = $_POST['senha']                ?? '';
$confirmar_senha = $_POST['confirmar_senha']      ?? '';

if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'Token não fornecido.']);
    exit;
}
if (strlen($senha) < 8) {
    echo json_encode(['success' => false, 'message' => 'Senha deve ter no mínimo 8 caracteres.']);
    exit;
}
if ($senha !== $confirmar_senha) {
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expira > NOW() LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Link expirado ou inválido.']);
    exit;
}

$hash = password_hash($senha, PASSWORD_DEFAULT);
$null = null;
$stmt = $conn->prepare("UPDATE usuarios SET senha = ?, reset_token = NULL, reset_token_expira = NULL WHERE id = ?");
$stmt->bind_param('si', $hash, $user['id']);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Senha redefinida com sucesso!', 'redirect' => SITE_URL . '/login.php']);
