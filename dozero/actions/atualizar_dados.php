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

$modo       = trim($_POST['modo'] ?? 'perfil');
$usuario_id = (int) $_SESSION['usuario_id'];

if ($modo === 'senha') {
    $senha_atual         = $_POST['senha_atual']         ?? '';
    $nova_senha          = $_POST['nova_senha']          ?? '';
    $confirmar_nova_senha = $_POST['confirmar_nova_senha'] ?? '';

    if ($senha_atual === '' || $nova_senha === '' || $confirmar_nova_senha === '') {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
        exit;
    }
    if (strlen($nova_senha) < 8) {
        echo json_encode(['success' => false, 'message' => 'Nova senha deve ter no mínimo 8 caracteres.']);
        exit;
    }
    if ($nova_senha !== $confirmar_nova_senha) {
        echo json_encode(['success' => false, 'message' => 'As novas senhas não coincidem.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($senha_atual, $row['senha'])) {
        echo json_encode(['success' => false, 'message' => 'Senha atual incorreta.']);
        exit;
    }

    $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $usuario_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Senha atualizada com sucesso!']);
    exit;
}

// modo = perfil
$nome        = trim($_POST['nome']        ?? '');
$celular     = trim($_POST['celular']     ?? '');
$cep         = trim($_POST['cep']         ?? '');
$endereco    = trim($_POST['endereco']    ?? '');
$numero      = trim($_POST['numero']      ?? '');
$complemento = trim($_POST['complemento'] ?? '');
$bairro      = trim($_POST['bairro']      ?? '');
$cidade      = trim($_POST['cidade']      ?? '');
$estado      = trim($_POST['estado']      ?? '');

if ($nome === '') {
    echo json_encode(['success' => false, 'message' => 'Nome é obrigatório.']);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE usuarios SET nome=?, celular=?, cep=?, endereco=?, numero=?, complemento=?, bairro=?, cidade=?, estado=? WHERE id=?"
);
$stmt->bind_param('sssssssssi', $nome, $celular, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $usuario_id);
$stmt->execute();
$stmt->close();

$_SESSION['nome'] = $nome;

echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso!']);
