<?php
require_once 'conexao_bd.php';
header('Content-Type: application/json');

$token = trim($_POST['token'] ?? '');
$nova_senha = trim($_POST['nova_senha'] ?? '');
$confirmar_senha = trim($_POST['confirmar_senha'] ?? '');

// Validações
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

if (empty($nova_senha) || empty($confirmar_senha)) {
    echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos.']);
    exit;
}

if ($nova_senha !== $confirmar_senha) {
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
    exit;
}

if (strlen($nova_senha) < 6) {
    echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 6 caracteres.']);
    exit;
}

// Verifica se o token é válido e não expirou
$stmt = $mysqli->prepare("
    SELECT id 
    FROM usuarios 
    WHERE token_recuperacao = ? 
    AND token_expira > NOW()
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Token inválido ou expirado. Solicite um novo link.']);
    exit;
}

$usuario = $result->fetch_assoc();
$usuario_id = $usuario['id'];

// Hash da nova senha
$senha_hash = password_hash($nova_senha, PASSWORD_BCRYPT);

// Atualiza a senha e limpa o token
$update = $mysqli->prepare("
    UPDATE usuarios 
    SET senha = ?, 
        token_recuperacao = NULL, 
        token_expira = NULL 
    WHERE id = ?
");
$update->bind_param("si", $senha_hash, $usuario_id);

if ($update->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Senha redefinida com sucesso! Redirecionando...'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao redefinir senha. Tente novamente.'
    ]);
}

$mysqli->close();
?>