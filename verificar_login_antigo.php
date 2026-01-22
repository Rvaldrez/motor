<?php
session_start();
require_once "conexao_bd.php";

$cpf_email = trim($_POST['cpf_email'] ?? '');
$senha     = trim($_POST['senha'] ?? '');

// 🔒 Verifica se campos estão preenchidos
if (empty($cpf_email) || empty($senha)) {
    header("Location: login.php?erro=" . urlencode("⚠️ Preencha todos os campos!"));
    exit;
}

// 🔍 Busca usuário por e-mail ou CPF
$stmt = $mysqli->prepare("SELECT id, nome, senha, tipo FROM usuarios WHERE email = ? OR cpf = ?");
$stmt->bind_param("ss", $cpf_email, $cpf_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario = $result->fetch_assoc();

    // 🔑 Verifica senha
    if (password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];

        // 🚀 Redireciona conforme tipo de usuário
switch ($usuario['tipo']) {
    case 'vendedor':
    case 'investidor':
        header("Location: painel_veiculos.php");
        break;
    case 'administrador':
        header("Location: painel_administrador.php");
        break;
    default:
        header("Location: login.php?erro=" . urlencode("Tipo de usuário inválido."));
}
        exit;
    } else {
        // ❌ Senha incorreta
        header("Location: login.php?erro=" . urlencode("❌ Senha incorreta!"));
        exit;
    }
} else {
    // ❌ Usuário não encontrado
    header("Location: login.php?erro=" . urlencode("❌ Usuário não encontrado!"));
    exit;
}
