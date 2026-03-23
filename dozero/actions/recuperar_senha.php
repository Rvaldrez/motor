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

$email = trim($_POST['email'] ?? '');
$msg   = 'Se o e-mail estiver cadastrado, você receberá as instruções em breve.';

if (!validateEmail($email)) {
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}

$token  = generateToken(64);
$expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

$stmt = $conn->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expira = ? WHERE id = ?");
$stmt->bind_param('ssi', $token, $expira, $user['id']);
$stmt->execute();
$stmt->close();

$link     = SITE_URL . '/redefinir_senha.php?token=' . $token;
$htmlBody = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
  <h2 style='color:#1a1a2e'>Redefinir senha – MotorGo</h2>
  <p>Olá, {$user['nome']}. Clique no botão abaixo para redefinir sua senha:</p>
  <p style='text-align:center'>
    <a href='{$link}' style='background:#e63946;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold'>Redefinir senha</a>
  </p>
  <p style='color:#666;font-size:13px'>O link expira em 1 hora. Se não foi você, ignore este e-mail.</p>
</div>";
sendEmail($email, $user['nome'], 'MotorGo – Redefinir senha', $htmlBody);

echo json_encode(['success' => true, 'message' => $msg]);
