<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($email === '' || $senha === '') {
    echo json_encode(['success' => false, 'message' => 'Preencha e-mail e senha.']);
    exit;
}
if (!validateEmail($email)) {
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, email, senha, tipo, status_confirmacao, status_cadastro FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'E-mail ou senha inválidos.']);
    exit;
}
if ($user['status_confirmacao'] === 'pendente') {
    echo json_encode(['success' => false, 'message' => 'Confirme seu e-mail antes de entrar.', 'redirect_to' => 'confirmar_email']);
    exit;
}
if (!password_verify($senha, $user['senha'])) {
    echo json_encode(['success' => false, 'message' => 'E-mail ou senha inválidos.']);
    exit;
}

// Preenche campos opcionais que podem não existir no BD legado
$user['status'] = $user['status'] ?? '';
$user['foto']   = $user['foto']   ?? '';

setUserSession($user);

echo json_encode(['success' => true, 'message' => 'Login realizado!', 'redirect' => '../painel.php']);
