<?php
require 'conexao_bd.php';
header('Content-Type: application/json');

$token = $_POST['token'] ?? '';
$nova = $_POST['nova_senha'] ?? '';
$confirma = $_POST['confirmar_senha'] ?? '';

// Validação básica
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => '❌ Token não informado.']);
    exit;
}

if (empty($nova) || empty($confirma)) {
    echo json_encode(['success' => false, 'message' => '⚠️ Preencha todos os campos da senha.']);
    exit;
}

if ($nova !== $confirma) {
    echo json_encode(['success' => false, 'message' => '❌ As senhas não coincidem.']);
    exit;
}

// Verifica se o token é válido e ainda não expirou
$sql = $mysqli->prepare("SELECT id FROM usuarios WHERE token_recuperacao = ? AND token_expira > NOW()");
$sql->bind_param("s", $token);
$sql->execute();
$resultado = $sql->get_result();

if ($resultado->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => '❌ Token inválido ou expirado.']);
    exit;
}

$usuario = $resultado->fetch_assoc();
$novaHash = password_hash($nova, PASSWORD_DEFAULT);

// Atualiza a senha e limpa o token
$update = $mysqli->prepare("UPDATE usuarios SET senha = ?, token_recuperacao = NULL, token_expira = NULL WHERE id = ?");
$update->bind_param("si", $novaHash, $usuario['id']);

if ($update->execute()) {
    echo json_encode(['success' => true, 'message' => '✅ Senha redefinida com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => '❌ Erro ao salvar a nova senha.']);
}
