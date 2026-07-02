<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$assunto   = trim($_POST['assunto']   ?? '');
$mensagem  = trim($_POST['mensagem']  ?? '');
$nome      = $_SESSION['nome']  ?? 'Usuário';
$email     = $_SESSION['email'] ?? '';

if ($assunto === '' || $mensagem === '') {
    echo json_encode(['success' => false, 'message' => 'Assunto e mensagem são obrigatórios.']);
    exit;
}

$htmlBody = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
  <h2 style='color:#1a1a2e'>Contato via MotorGo</h2>
  <p><strong>Nome:</strong> " . htmlspecialchars($nome,     ENT_QUOTES, 'UTF-8') . "</p>
  <p><strong>E-mail:</strong> " . htmlspecialchars($email,  ENT_QUOTES, 'UTF-8') . "</p>
  <p><strong>Assunto:</strong> " . htmlspecialchars($assunto, ENT_QUOTES, 'UTF-8') . "</p>
  <hr>
  <p>" . nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8')) . "</p>
</div>";

$enviado = sendEmail('contato@motorgo.co', 'MotorGo', 'Contato: ' . $assunto, $htmlBody);

if (!$enviado) {
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar mensagem. Tente novamente.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso! Responderemos em breve.']);
